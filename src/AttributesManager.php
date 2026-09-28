<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Actions\PruneAttributesAction;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

/**
 * The root of the `Attributes` facade and the injectable entry point to the
 * package: definitions and validation (delegated to the AttributeRegistry
 * singleton), an owner handle for reading and writing a model's attributes,
 * and pruning.
 *
 * Not final: the recording fake extends it.
 */
class AttributesManager
{
    public function __construct(
        protected readonly Container $container,
        protected readonly AttributeRegistry $registry,
    ) {}

    /**
     * Read and write one owner's attributes.
     *
     * @param  Model&HasAttributes  $owner
     */
    public function for(Model $owner): OwnerAttributes
    {
        return new OwnerAttributes($this->container, $owner);
    }

    /**
     * Permanently delete soft-deleted attributes trashed more than `$days`
     * days ago (default: `attributes.prune_after_days`). Returns the count.
     */
    public function prune(?int $days = null): int
    {
        return $this->container->make(PruneAttributesAction::class)->execute($days);
    }

    // Definitions ------------------------------------------------------------

    public function define(AttributeDefinitionData $definition): static
    {
        $this->registry->define($definition);

        return $this;
    }

    public function defineMany(AttributeDefinitionData ...$definitions): static
    {
        $this->registry->defineMany(...$definitions);

        return $this;
    }

    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    public function get(string $name): ?AttributeDefinitionData
    {
        return $this->registry->get($name);
    }

    /**
     * @return array<string, AttributeDefinitionData>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * Remove a global definition (not a stored value — see `for($owner)->forget()`).
     */
    public function forget(string $name): static
    {
        $this->registry->forget($name);

        return $this;
    }

    public function flush(): static
    {
        $this->registry->flush();

        return $this;
    }

    /**
     * The definition for a name, preferring the owner's model-declared one.
     */
    public function resolveFor(?Model $owner, string $name): ?AttributeDefinitionData
    {
        return $this->registry->resolveFor($owner, $name);
    }

    /**
     * @return array<string, AttributeDefinitionData>
     */
    public function definitionsFor(Model $owner): array
    {
        return $this->registry->definitionsFor($owner);
    }

    public function default(string $name, ?Model $owner = null): mixed
    {
        return $this->registry->default($name, $owner);
    }

    /**
     * @return list<string>
     */
    public function requiredNames(): array
    {
        return $this->registry->requiredNames();
    }

    // Validation ---------------------------------------------------------------

    /**
     * @throws InvalidAttributeValueException
     */
    public function validate(string $name, mixed $value): void
    {
        $this->registry->validate($name, $value);
    }

    /**
     * @throws InvalidAttributeValueException
     */
    public function validateFor(?Model $owner, string $name, mixed $value): void
    {
        $this->registry->validateFor($owner, $name, $value);
    }

    /**
     * @throws DuplicateAttributeValueException
     */
    public function assertUnique(Model $owner, string $name, mixed $value): void
    {
        $this->registry->assertUnique($owner, $name, $value);
    }

    public function isStrict(): bool
    {
        return $this->registry->isStrict();
    }

    /**
     * Reject a name strict mode does not know — a global definition, or given
     * the owner, one in the owner's own schema.
     *
     * @throws UnknownAttributeException
     */
    public function assertKnown(string $name, ?Model $owner = null): void
    {
        $this->registry->assertKnown($name, $owner);
    }
}
