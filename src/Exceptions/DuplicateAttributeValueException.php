<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

use RoundlyConsulting\Attributes\Enums\UniqueScope;

final class DuplicateAttributeValueException extends AttributesException
{
    public ?string $attributeName = null;

    public ?UniqueScope $scope = null;

    public static function forName(string $name, UniqueScope $scope): self
    {
        $exception = new self(
            "Attribute [{$name}] must be unique ({$scope->value}); the given value already exists.",
        );

        $exception->attributeName = $name;
        $exception->scope = $scope;

        return $exception;
    }
}
