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
use RoundlyConsulting\Attributes\Support\AttributesConfig;
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
     * Validate an owner's whole attribute set in one pass, so a definition's rules
     * may refer to other attributes (`required_if:type,physical`, `gte:min_price`).
     * `$values` is the complete set (name => value) the owner would hold. Every
     * definition's rules run — a defined name absent from the set too, so implicit
     * rules (`required_if`, `required_with`) fire — with `nullable` and the type rule
     * prepended: a null value passes every rule but the implicit ones. Free-form
     * names carry no rules but can be referred to.
     *
     * A name with a dot is taken literally; a rule referring to one escapes it
     * (`gte:dimensions\.width`).
     *
     * @param  array<string, mixed>  $values
     *
     * @throws InvalidAttributeValueException with an error bag keyed by attribute name
     */
    public function validateSet(Model $owner, array $values): void
    {
        $rules = [];
        $names = [];

        foreach ($this->definitionsFor($owner) as $name => $definition) {
            $key = str_replace('.', '\\.', $name);

            $rules[$key] = ['nullable', $definition->type->validationRule(), ...$definition->rules];
            $names[$name] = $name;
        }

        if ($rules === []) {
            return;
        }

        $validator = Validator::make($values, $rules, [], $names);

        if ($validator->fails()) {
            throw InvalidAttributeValueException::failedSetValidation($validator->errors());
        }
    }

    /**
     * Whether every value write validates the owner's whole set
     * (`attributes.validation.whole_set`).
     */
    public function validatesWholeSet(): bool
    {
        return AttributesConfig::validatesWholeSet();
    }

    /**
     * Everything a whole-set write must pass: strict mode for each written name, the
     * set the owner holds after the write validated as one, and each written value's
     * unique scope. The set is the owner's stored values overlaid by `$values` — or,
     * with `$replace` (a sync), `$values` alone. Call it inside the write's
     * transaction, after the owner's row is locked, so the stored values it reads are
     * the ones the write lands on.
     *
     * @param  array<string, mixed>  $values  name => value being written
     *
     * @throws UnknownAttributeException
     * @throws InvalidAttributeValueException
     * @throws DuplicateAttributeValueException
     */
    public function assertWritableSet(Model $owner, array $values, bool $replace = false): void
    {
        foreach (array_keys($values) as $name) {
            $this->assertKnown((string) $name, $owner);
        }

        $set = $replace ? $values : array_replace($this->storedValues($owner), $values);

        $this->validateSet($owner, $set);

        foreach ($values as $name => $value) {
            $this->assertUnique($owner, (string) $name, $value);
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
            $definitions[(string) $name] = DefinitionFactory::fromRaw((string) $name, $definition, $owner::class.'::attributeDefinitions');
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

    /**
     * The owner's stored values, read fresh (never an eager-loaded relation).
     *
     * @return array<string, mixed>
     */
    private function storedValues(Model $owner): array
    {
        $values = [];

        $rows = $this->attributeModel()->newQuery()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->get();

        foreach ($rows as $attribute) {
            // The value cast resolves the definition (type, encryption) through the owner.
            $attribute->setRelation('owner', $owner);
            $values[$attribute->name] = $attribute->value;
        }

        return $values;
    }

    private function attributeModel(): Attribute
    {
        $model = AttributeModel::class();

        return new $model;
    }
}
