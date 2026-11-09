<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

use Illuminate\Support\MessageBag;
use Throwable;

final class InvalidAttributeValueException extends AttributesException
{
    public ?string $attributeName = null;

    private ?MessageBag $errors = null;

    /**
     * Whether the error bag is keyed by attribute name (a whole-set validation)
     * rather than holding one attribute's messages under `value`.
     */
    private bool $keyedByName = false;

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

    /**
     * A whole-set validation failed: the error bag is keyed by attribute name, and
     * `attributeName` is the first attribute that failed.
     */
    public static function failedSetValidation(MessageBag $errors): self
    {
        $name = (string) array_key_first($errors->messages());

        $exception = new self("Attribute [{$name}] failed validation: ".$errors->first($name));

        $exception->attributeName = $name;
        $exception->errors = $errors;
        $exception->keyedByName = true;

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

        if ($this->keyedByName) {
            $messages = [];

            foreach ($this->errors->messages() as $key => $list) {
                foreach ($list as $message) {
                    $messages[] = "{$key}: {$message}";
                }
            }

            return $messages;
        }

        $name = $this->attributeName ?? 'value';

        return array_values(array_map(
            fn (string $message): string => "{$name}: {$message}",
            $this->errors->all(),
        ));
    }
}
