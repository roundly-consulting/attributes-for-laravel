<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;

/**
 * Chat review C-11: the `encrypted()` state set only the flag, so the plaintext was
 * stored as if it were ciphertext and reading `->value` threw DecryptException.
 */
it('stores ciphertext together with the encrypted flag', function (): void {
    $attribute = Attribute::factory()->encrypted()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'value' => 'et',
    ]);

    $row = DB::table('attributes')->where('id', $attribute->getKey())->first();

    expect((bool) $row->is_encrypted)->toBeTrue()
        ->and($row->value)->not->toBe('et')
        ->and(Crypt::decryptString($row->value))->toBe('et')
        ->and($attribute->fresh()->value)->toBe('et');
});

it('keeps a null value null under the encrypted state', function (): void {
    $attribute = Attribute::factory()->encrypted()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'value' => null,
    ]);

    expect($attribute->fresh()->value)->toBeNull()
        ->and($attribute->fresh()->is_encrypted)->toBeTrue();
});

it('does not encrypt twice a value its definition already encrypts', function (): void {
    Attributes::define(new AttributeDefinitionData('pin', AttributeType::String_, encrypted: true));

    $attribute = Attribute::factory()->encrypted()->create([
        'name' => 'pin',
        'value' => '1234',
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    expect($attribute->fresh()->value)->toBe('1234');
});
