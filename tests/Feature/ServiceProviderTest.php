<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

it('registers all publish tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(null, $tag);

    expect($paths)->not->toBeEmpty();
})->with([
    'attributes-config',
    'attributes-migrations',
]);

it('binds the attribute registry as a singleton', function (): void {
    expect(app(AttributeRegistry::class))->toBe(app(AttributeRegistry::class));
});

it('registers the package commands', function (string $command): void {
    expect(array_keys(app('Illuminate\Contracts\Console\Kernel')->all()))->toContain($command);
})->with([
    'attributes:list',
    'attributes:prune',
]);

it('contributes an attributes section to about', function (string $expected): void {
    $this->artisan('about --only=attributes')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Attributes',
    'Model',
    'Table',
    'Strict mode',
    'History',
    'Prune after',
    'Definitions',
]);

it('reports definitions by count and never prints their names', function (): void {
    config()->set('attributes.definitions', [
        'api_token' => ['type' => 'string', 'encrypted' => true],
        'rating' => ['type' => 'integer'],
    ]);

    $this->artisan('about --only=attributes')
        ->expectsOutputToContain('2 defined (1 encrypted)')
        ->doesntExpectOutputToContain('api_token')
        ->assertExitCode(0);
});

it('reports free-form definitions when none are configured', function (): void {
    $this->artisan('about --only=attributes')
        ->expectsOutputToContain('FREE-FORM')
        ->assertExitCode(0);
});
