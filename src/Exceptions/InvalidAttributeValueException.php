<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

use Illuminate\Support\MessageBag;

final class InvalidAttributeValueException extends AttributesException
{
    public static function forName(string $name, string $reason): self
    {
        return new self("Attribute [{$name}] has an invalid value: {$reason}");
    }

    public static function failedValidation(string $name, MessageBag $errors): self
    {
        return new self(
            "Attribute [{$name}] failed validation: ".$errors->first('value'),
        );
    }
}
