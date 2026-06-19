<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\Models\Attribute;

final class SyncAttributeMetaAction
{
    /**
     * @param  Model&HasAttributes  $owner
     * @param  Collection<string, mixed>|null  $meta
     */
    public function execute(Model $owner, string $name, ?Collection $meta): Attribute
    {
        return $owner->attachedAttributes()->updateOrCreate(
            ['name' => $name],
            ['meta' => $meta],
        );
    }
}
