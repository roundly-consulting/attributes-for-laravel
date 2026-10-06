<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AttributesException extends RuntimeException
{
    /**
     * A write to an owner that has no row (never saved, or deleted): its attributes
     * would hang off no key and be unreachable.
     */
    public static function unsavedOwner(string $name, Model $owner): self
    {
        return new self(sprintf(
            'Attribute [%s] cannot be written to an unsaved owner [%s]; save the owner first.',
            $name,
            $owner::class,
        ));
    }
}
