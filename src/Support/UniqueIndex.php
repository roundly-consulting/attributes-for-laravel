<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Support\Facades\Crypt;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Crypto\Hash\Digest;
use RoundlyConsulting\Crypto\Hash\Hmac;

/**
 * The deterministic `unique_hash` of a value under a unique definition. A unique
 * index on that column is what turns `unique` into a database-level guarantee:
 * two concurrent writers of the same value cannot both commit.
 *
 * The hash covers the scope, the owner type (owner scope only), the name and the
 * plain storage form. For an **encrypted** definition it is keyed — an HMAC under
 * a key derived from the app key — so the index is a blind index: equal values
 * still collide, but the column reveals nothing a dictionary attack could use.
 * Plain values use an unkeyed digest (their text is in the `value` column anyway),
 * which also keeps them independent of APP_KEY rotation.
 *
 * @internal written by the AttributeValue cast, read by the registry's pre-check
 */
final class UniqueIndex
{
    /**
     * The derivation context, so the blind-index key is never the encryption key itself.
     */
    private const string CONTEXT = 'roundly-consulting/attributes-for-laravel:unique-index:v1';

    /**
     * The hash for a value, or null when the definition does not enforce
     * uniqueness or the value is null (nulls never collide).
     */
    public static function for(AttributeDefinitionData $definition, ?string $ownerType, ?string $plainValue): ?string
    {
        if (! $definition->unique->enforces() || $plainValue === null) {
            return null;
        }

        $payload = self::encode([
            $definition->unique->value,
            $definition->unique === UniqueScope::Owner ? (string) $ownerType : '',
            $definition->name,
            $plainValue,
        ]);

        return new Digest()->withPepper($payload, $definition->encrypted ? self::key() : null);
    }

    /**
     * Length-prefixed, so no value can forge a separator (binary-safe, unlike JSON).
     *
     * @param  list<string>  $parts
     */
    private static function encode(array $parts): string
    {
        return implode('', array_map(static fn (string $part): string => strlen($part).':'.$part, $parts));
    }

    private static function key(): string
    {
        return new Hmac()->sign(self::CONTEXT, Crypt::getKey());
    }
}
