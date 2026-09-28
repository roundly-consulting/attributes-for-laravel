<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

use RoundlyConsulting\Attributes\Enums\UniqueScope;
use Throwable;

final class DuplicateAttributeValueException extends AttributesException
{
    public ?string $attributeName = null;

    public ?UniqueScope $scope = null;

    public static function forName(string $name, UniqueScope $scope, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Attribute [{$name}] must be unique ({$scope->value}); the given value already exists.",
            0,
            $previous,
        );

        $exception->attributeName = $name;
        $exception->scope = $scope;

        return $exception;
    }
}
