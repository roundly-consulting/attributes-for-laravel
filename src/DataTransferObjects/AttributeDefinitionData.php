<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\DataTransferObjects;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;

final readonly class AttributeDefinitionData
{
    /**
     * `$uniqueScope` names what a `UniqueScope::Scope` value is unique within: an
     * attribute or relation name on the owner (`team_id`, `team`), a
     * `ResolvesUniqueScope` class-string, or a closure given the owner. Null falls
     * back to `attributes.unique_scope`.
     *
     * @param  list<string|object>  $rules
     * @param  string|(Closure(Model): mixed)|null  $uniqueScope
     */
    public function __construct(
        public string $name,
        public AttributeType $type,
        public array $rules = [],
        public mixed $default = null,
        public bool $required = false,
        public UniqueScope $unique = UniqueScope::None,
        public bool $encrypted = false,
        public string|Closure|null $uniqueScope = null,
    ) {}
}
