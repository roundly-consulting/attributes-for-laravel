<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Registry;

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;

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
     * Validate a value against its definition. No-op when the key is undefined.
     *
     * @throws InvalidAttributeValueException
     */
    public function validate(string $name, mixed $value): void
    {
        $definition = $this->get($name);

        if ($definition === null) {
            return;
        }

        $rules = array_merge([$definition->type->validationRule()], $definition->rules);

        $validator = Validator::make(['value' => $value], ['value' => $rules]);

        if ($validator->fails()) {
            throw InvalidAttributeValueException::failedValidation($name, $validator->errors());
        }
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
}
