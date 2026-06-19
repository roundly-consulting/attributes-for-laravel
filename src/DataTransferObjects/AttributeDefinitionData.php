<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\DataTransferObjects;

use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;

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
        public UniqueScope $unique = UniqueScope::None,
        public bool $encrypted = false,
    ) {}
}
