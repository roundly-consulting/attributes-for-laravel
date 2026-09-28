<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

use Illuminate\Support\MessageBag;
use Throwable;

final class InvalidAttributeValueException extends AttributesException
{
    public ?string $attributeName = null;

    private ?MessageBag $errors = null;

    public static function forName(string $name, string $reason, ?Throwable $previous = null): self
    {
        $exception = new self("Attribute [{$name}] has an invalid value: {$reason}", 0, $previous);

        $exception->attributeName = $name;

        return $exception;
    }

    public static function failedValidation(string $name, MessageBag $errors): self
    {
        $exception = new self(
            "Attribute [{$name}] failed validation: ".$errors->first('value'),
        );

        $exception->attributeName = $name;
        $exception->errors = $errors;

        return $exception;
    }

    public function errorBag(): ?MessageBag
    {
        return $this->errors;
    }

    /**
     * Every validation message, each led by the attribute name.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        if ($this->errors === null) {
            return [];
        }

        $name = $this->attributeName ?? 'value';

        return array_values(array_map(
            fn (string $message): string => "{$name}: {$message}",
            $this->errors->all(),
        ));
    }
}
