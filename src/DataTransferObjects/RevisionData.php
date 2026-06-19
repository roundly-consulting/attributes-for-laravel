<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\DataTransferObjects;

use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\RevisionType;

final readonly class RevisionData
{
    /**
     * @param  Collection<string, mixed>|null  $oldMeta
     * @param  Collection<string, mixed>|null  $newMeta
     */
    public function __construct(
        public string $name,
        public RevisionType $type,
        public ?string $oldValue,
        public ?string $newValue,
        public ?AttributeType $oldValueType,
        public ?AttributeType $newValueType,
        public ?Collection $oldMeta = null,
        public ?Collection $newMeta = null,
    ) {}
}
