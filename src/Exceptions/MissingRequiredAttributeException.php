<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

final class MissingRequiredAttributeException extends AttributesException
{
    public ?string $attributeName = null;

    public static function forName(string $name): self
    {
        $exception = new self("Required attribute [{$name}] is missing.");

        $exception->attributeName = $name;

        return $exception;
    }
}
