<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeModel;

final class ListAttributesCommand extends Command
{
    protected $signature = 'attributes:list {owner-type : The owner morph type or class} {owner-id : The owner key}';

    protected $description = 'List the attributes attached to a given owner record';

    public function handle(): int
    {
        $model = AttributeModel::class();

        // Rows carry the morph alias under a morph map; a model class is mapped to it.
        $given = $this->stringArgument('owner-type');
        $ownerType = is_a($given, Model::class, true) ? Relation::getMorphAlias($given) : $given;
        $ownerId = $this->stringArgument('owner-id');

        $attributes = $model::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->get();

        if ($attributes->isEmpty()) {
            $this->info("No attributes found for [{$ownerType}#{$ownerId}].");

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Type', 'Value', 'Meta'],
            $attributes->map(static fn (Attribute $attribute): array => [
                $attribute->name,
                $attribute->type()->value,
                self::stringifyValue($attribute->value),
                $attribute->meta?->isNotEmpty() ? (string) json_encode($attribute->meta->all()) : '',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private static function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }
}
