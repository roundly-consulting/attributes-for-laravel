<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

final class UnknownAttributeException extends AttributesException
{
    public static function forName(string $name): self
    {
        return new self("Attribute [{$name}] is not registered.");
    }
}
