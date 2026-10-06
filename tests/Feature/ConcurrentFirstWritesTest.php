<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Two first writes racing for real: each runs in its own process on its own connection,
 * and both are held at the insert (the attribute's `creating` event) until the other one
 * has got there too, so every lock either took before inserting is held at the same time.
 *
 * Chat review C-1: the write used to look the row up with `SELECT … FOR UPDATE` before it
 * existed. On InnoDB (REPEATABLE READ) a locking read that finds nothing takes a gap
 * lock; two of them are compatible, but each blocks the other's insert, so the two
 * inserts deadlocked (1213) instead of one updating the winner's row. Owners whose keys
 * fall into the same gap collided too, not only one owner writing one name twice.
 *
 * Needs a real engine (separate processes cannot share sqlite `:memory:`) and pcntl.
 *
 * @return array{0: string, 1: string} the parent's and the child's outcome
 */
function attributesRaceFirstWrites(Product $first, string $firstValue, Product $second, string $secondValue): array
{
    $root = sys_get_temp_dir().'/attributes-race-'.bin2hex(random_bytes(6));
    $barrier = $root.'/arrived';
    mkdir($barrier, recursive: true);

    $arrived = false;

    Attribute::creating(static function () use ($barrier, &$arrived): void {
        if ($arrived) {
            return;
        }

        $arrived = true;
        touch($barrier.'/'.getmypid());

        $deadline = microtime(true) + 10;

        while (count(glob($barrier.'/*') ?: []) < 2 && microtime(true) < $deadline) {
            usleep(5_000);
        }
    });

    $write = static function (Product $owner, string $value): string {
        try {
            Attributes::for($owner)->set('color', $value);

            return 'ok';
        } catch (Throwable $exception) {
            return $exception::class.': '.$exception->getMessage();
        }
    };

    // Neither process may inherit an open connection: both reconnect on their own.
    DB::purge();

    $pid = pcntl_fork();

    if ($pid === 0) {
        file_put_contents($root.'/child', $write($second, $secondValue));

        // No shutdown handlers, no destructors: the parent owns the test run.
        posix_kill(posix_getpid(), SIGKILL);
    }

    $parent = $write($first, $firstValue);

    pcntl_waitpid($pid, $status);

    $child = (string) @file_get_contents($root.'/child');

    array_map('unlink', glob($barrier.'/*') ?: []);
    rmdir($barrier);
    @unlink($root.'/child');
    rmdir($root);

    return [$parent, $child];
}

$withoutRealEngine = fn (): bool => DriverMatrix::driver() === 'sqlite' || ! function_exists('pcntl_fork');

it('settles two owners racing for the same index gap without a deadlock', function (): void {
    $first = Product::query()->create();
    $second = Product::query()->create();

    expect(attributesRaceFirstWrites($first, 'red', $second, 'blue'))->toBe(['ok', 'ok']);

    expect($first->getAttachedAttributeValue('color'))->toBe('red')
        ->and($second->getAttachedAttributeValue('color'))->toBe('blue')
        ->and(Attribute::query()->count())->toBe(2);
})->skip($withoutRealEngine, 'needs a real engine and pcntl');

it('turns one owner\'s two racing first writes into an insert and an update', function (): void {
    $product = Product::query()->create();

    expect(attributesRaceFirstWrites($product, 'red', $product, 'blue'))->toBe(['ok', 'ok']);

    $rows = Attribute::query()->where('name', 'color')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()?->value)->toBeIn(['red', 'blue']);
})->skip($withoutRealEngine, 'needs a real engine and pcntl');
