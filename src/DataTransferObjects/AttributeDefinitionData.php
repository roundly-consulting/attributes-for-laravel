<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\DataTransferObjects;

use RoundlyConsulting\Attributes\Enums\AttributeType;

final readonly class AttributeDefinitionData
{
    /**
     * @param  list<string|object>  $rules
     */
    public function __construct(
        public string $name,
        public AttributeType $type,
        public array $rules = [],
        public mixed $default = null,
        public bool $required = false,
    ) {}
}
