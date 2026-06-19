<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\DataTransferObjects;

use RoundlyConsulting\Attributes\Enums\AttributeType;

final readonly class StoredValue
{
    public function __construct(
        public ?string $value,
        public AttributeType $type,
    ) {}
}
