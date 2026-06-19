<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Registry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeQuery;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;

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
     * Enforce a definition's uniqueness scope before a value is stored.
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

        $stored = new AttributeValueCaster()->toStorage($value, $definition->type)->value;

        $query = $this->attributeModel()->newQuery()
            ->where('name', $name)
            ->where('value', $stored);

        if ($definition->unique === UniqueScope::Owner) {
            $query->where('owner_type', $owner->getMorphClass());
        }

        $query->where(function ($sub) use ($owner): void {
            $sub->where('owner_type', '!=', $owner->getMorphClass())
                ->orWhere('owner_id', '!=', $owner->getKey());
        });

        if ($query->exists()) {
            throw DuplicateAttributeValueException::forName($name, $definition->unique);
        }
    }

    /**
     * Build a bulk read helper for an owner's attributes.
     *
     * @param  Model&HasAttributes  $owner
     */
    public function for(Model $owner): AttributeQuery
    {
        return new AttributeQuery($owner);
    }

    public function isStrict(): bool
    {
        return (bool) config('attributes.strict', false);
    }

    /**
     * Reject unknown keys when strict mode is enabled.
     *
     * @throws UnknownAttributeException
     */
    public function assertKnown(string $name): void
    {
        if ($this->isStrict() && ! $this->has($name)) {
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
        /** @var class-string<Attribute> $model */
        $model = config('attributes.model', Attribute::class);

        return new $model;
    }
}
