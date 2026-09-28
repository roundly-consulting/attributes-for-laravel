<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RoundlyConsulting\Attributes\DataTransferObjects\StoredValue;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\PackageToolkit\Support\RawExpression;

/**
 * The typed comparisons behind the `HasAttributes` query scopes.
 *
 * A needle is stored the way a value would be — in the name's defined type when
 * the owner model (or the global registry) defines one, else in the type inferred
 * from the PHP value — and compared together with that type: integer and float
 * form one numeric family, every other type matches only itself. Numbers compare
 * and sort as numbers through a guarded CAST; everything else compares as its
 * storage text, which for datetimes is UTC ISO-8601 and so chronological.
 *
 * @internal used by the HasAttributes scopes
 */
final class TypedValueQuery
{
    /**
     * The storage form of a needle for `$name` on `$owner`'s model.
     */
    public static function needle(Model $owner, string $name, mixed $value): StoredValue
    {
        $caster = new AttributeValueCaster;

        $type = app(AttributeRegistry::class)->resolveFor($owner, $name)?->type;

        if ($type !== null) {
            try {
                return $caster->plain($value, $type);
            } catch (InvalidAttributeValueException) {
                // A needle the defined type cannot take is compared as what it is —
                // and so matches no row of that type.
            }
        }

        return $caster->plain($value);
    }

    /**
     * The `value_type` values a needle of `$type` matches.
     *
     * @return list<string>
     */
    public static function family(AttributeType $type): array
    {
        return $type->isNumeric()
            ? [AttributeType::Integer->value, AttributeType::Float_->value]
            : [$type->value];
    }

    /**
     * Constrain an attribute query to values equal to the needle (a `null` needle
     * matches a stored null of any type).
     *
     * @param  Builder<Attribute>  $query
     */
    public static function whereEquals(Builder $query, StoredValue $needle): void
    {
        if ($needle->value === null) {
            $query->whereNull($query->qualifyColumn('value'));

            return;
        }

        $query->whereIn($query->qualifyColumn('value_type'), self::family($needle->type))
            ->where($query->qualifyColumn('value'), $needle->value);
    }

    /**
     * A numeric view of the stored value: the number for a plain integer/float
     * row, NULL for every other row. The CASE guards the CAST — no engine is ever
     * asked to turn a string, or an encrypted ciphertext, into a number.
     */
    public static function numeric(QueryBuilder $query, string $table): RawExpression
    {
        $grammar = $query->getGrammar();
        $driver = self::driver($query);

        return new RawExpression(sprintf(
            "case when %s in ('%s', '%s') and %s = %s then cast(%s as %s) end",
            $grammar->wrap($table.'.value_type'),
            AttributeType::Integer->value,
            AttributeType::Float_->value,
            $grammar->wrap($table.'.is_encrypted'),
            $driver === 'pgsql' ? 'false' : '0',
            $grammar->wrap($table.'.value'),
            self::numericType($driver),
        ));
    }

    /**
     * Constrain an attribute query to numeric values within the (inclusive)
     * bounds. The bounds are bound as their storage strings and cast on the
     * engine too — SQLite would otherwise compare a number with text.
     *
     * @param  Builder<Attribute>  $query
     */
    public static function whereNumericBetween(Builder $query, string $low, string $high): void
    {
        $base = $query->getQuery();
        $type = self::numericType(self::driver($base));

        $query->whereRaw(
            new RawExpression(
                self::numeric($base, $query->getModel()->getTable())->getValue($base->getGrammar())
                    ." between cast(? as {$type}) and cast(? as {$type})",
            ),
            [$low, $high],
        );
    }

    private static function driver(QueryBuilder $query): string
    {
        $connection = $query->getConnection();

        return $connection instanceof Connection ? $connection->getDriverName() : '';
    }

    private static function numericType(string $driver): string
    {
        return match ($driver) {
            'pgsql' => 'double precision',
            'mysql', 'mariadb' => 'decimal(65, 30)',
            'sqlsrv' => 'float',
            default => 'real',
        };
    }
}
