<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Registry;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;
use RoundlyConsulting\Attributes\Support\UniqueIndex;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * The attribute definitions (global and model-declared) and their validation.
 * A container singleton the actions validate against; host code reaches it
 * through the `Attributes` facade (AttributesManager delegates here).
 */
final class AttributeRegistry
{
    /**
     * @var array<string, AttributeDefinitionData>
     */
    private array $definitions = [];

    public function define(AttributeDefinitionData $definition): self
    {
        $this->definitions[$definition->name] = $definition;

        return $this;
    }

    public function defineMany(AttributeDefinitionData ...$definitions): self
    {
        foreach ($definitions as $definition) {
            $this->define($definition);
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->definitions);
    }

    public function get(string $name): ?AttributeDefinitionData
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * @return array<string, AttributeDefinitionData>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function forget(string $name): self
    {
        unset($this->definitions[$name]);

        return $this;
    }

    public function flush(): self
    {
        $this->definitions = [];

        return $this;
    }

    /**
     * Resolve the definition for a name, preferring a model-declared one.
     *
     * Per-model definitions are merged on demand; the shared singleton's
     * global set is never mutated.
     */
    public function resolveFor(?Model $owner, string $name): ?AttributeDefinitionData
    {
        if ($owner !== null) {
            $modelDefinitions = $this->modelDefinitions($owner);

            if (array_key_exists($name, $modelDefinitions)) {
                return $modelDefinitions[$name];
            }
        }

        return $this->get($name);
    }

    /**
     * The merged definition map for an owner (global overlaid by model-declared).
     *
     * @return array<string, AttributeDefinitionData>
     */
    public function definitionsFor(Model $owner): array
    {
        return array_merge($this->definitions, $this->modelDefinitions($owner));
    }

    /**
     * The default value declared for a name, optionally scoped to an owner.
     */
    public function default(string $name, ?Model $owner = null): mixed
    {
        return $this->resolveFor($owner, $name)?->default;
    }

    /**
     * Names of every required global definition.
     *
     * @return list<string>
     */
    public function requiredNames(): array
    {
        $names = [];

        foreach ($this->definitions as $definition) {
            if ($definition->required) {
                $names[] = $definition->name;
            }
        }

        return $names;
    }

    /**
     * Validate a value against its definition. No-op when the key is undefined.
     *
     * @throws InvalidAttributeValueException
     */
    public function validate(string $name, mixed $value): void
    {
        $this->validateFor(null, $name, $value);
    }

    /**
     * Validate a value against its definition, honouring per-model schemas.
     *
     * @throws InvalidAttributeValueException
     */
    public function validateFor(?Model $owner, string $name, mixed $value): void
    {
        $definition = $this->resolveFor($owner, $name);

        if ($definition === null || $value === null) {
            return;
        }

        $rules = array_merge([$definition->type->validationRule()], $definition->rules);

        $validator = Validator::make(
            ['value' => $value],
            ['value' => $rules],
            [],
            ['value' => $name],
        );

        if ($validator->fails()) {
            throw InvalidAttributeValueException::failedValidation($name, $validator->errors());
        }
    }

    /**
     * Everything a write must pass before it touches the database: strict mode
     * (honouring the owner's own schema), the definition's rules and its unique
     * scope. The batch writers run it for every item before the first write.
     *
     * @throws UnknownAttributeException
     * @throws InvalidAttributeValueException
     * @throws DuplicateAttributeValueException
     */
    public function assertWritable(Model $owner, string $name, mixed $value): void
    {
        $this->assertKnown($name, $owner);
        $this->validateFor($owner, $name, $value);
        $this->assertUnique($owner, $name, $value);
    }

    /**
     * Enforce a definition's uniqueness scope before a value is stored — works for
     * encrypted values too, through their deterministic `unique_hash`. The unique
     * index on that column is the real guarantee; this check turns the common case
     * into a friendly exception before any write.
     *
     * Allows re-saving the owner's own existing value (idempotent update).
     *
     * @throws DuplicateAttributeValueException
     */
    public function assertUnique(Model $owner, string $name, mixed $value): void
    {
        $definition = $this->resolveFor($owner, $name);

        if ($definition === null || ! $definition->unique->enforces() || $value === null) {
            return;
        }

        try {
            $plain = new AttributeValueCaster()->plain($value, $definition->type)->value;
        } catch (InvalidAttributeValueException $exception) {
            throw InvalidAttributeValueException::forName($name, "expected a value of type [{$definition->type->value}]", $exception);
        }

        $hash = UniqueIndex::for($definition, $owner->getMorphClass(), $plain);

        $query = $this->attributeModel()->newQuery()
            ->where('name', $name)
            ->where(function (Builder $match) use ($hash, $plain, $definition): void {
                $match->where('unique_hash', $hash);

                // A plain value stored before its definition became unique has no
                // hash yet; its text is still comparable.
                if (! $definition->encrypted) {
                    $match->orWhere(fn (Builder $legacy): Builder => $legacy
                        ->whereNull('unique_hash')
                        ->where('is_encrypted', false)
                        ->where('value', $plain));
                }
            });

        if ($definition->unique === UniqueScope::Owner) {
            $query->where('owner_type', $owner->getMorphClass());
        }

        $query->where(function (Builder $sub) use ($owner): void {
            $sub->where('owner_type', '!=', $owner->getMorphClass())
                ->orWhere('owner_id', '!=', $owner->getKey());
        });

        if ($query->exists()) {
            throw DuplicateAttributeValueException::forName($name, $definition->unique);
        }
    }

    public function isStrict(): bool
    {
        return Config::boolean('attributes.strict');
    }

    /**
     * Reject unknown keys when strict mode is enabled. A name is known when it has
     * a global definition or — given the owner — one in the owner's own schema.
     *
     * @throws UnknownAttributeException
     */
    public function assertKnown(string $name, ?Model $owner = null): void
    {
        if ($this->isStrict() && $this->resolveFor($owner, $name) === null) {
            throw UnknownAttributeException::forName($name);
        }
    }

    /**
     * Read a model's own attribute definitions, parsed through the shared factory.
     *
     * A model opts in by declaring a public attributeDefinitions() method or a
     * protected/public array property of the same name.
     *
     * @return array<string, AttributeDefinitionData>
     */
    private function modelDefinitions(Model $owner): array
    {
        $raw = $this->rawModelDefinitions($owner);

        $definitions = [];

        foreach ($raw as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $definitions[(string) $name] = DefinitionFactory::fromArray((string) $name, $definition);
        }

        return $definitions;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function rawModelDefinitions(Model $owner): array
    {
        if (method_exists($owner, 'attributeDefinitions')) {
            /** @var mixed $defined */
            $defined = $owner->attributeDefinitions();

            return is_array($defined) ? $defined : [];
        }

        if (property_exists($owner, 'attributeDefinitions')) {
            /** @var mixed $defined */
            $defined = (fn (): mixed => $this->attributeDefinitions)->call($owner);

            return is_array($defined) ? $defined : [];
        }

        return [];
    }

    private function attributeModel(): Attribute
    {
        $model = AttributeModel::class();

        return new $model;
    }
}
