<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\Events\AttributeDetached;

final class DetachAttributesAction
{
    /**
     * @param  Model&HasAttributes  $owner
     * @param  list<string>  $names
     */
    public function execute(Model $owner, array $names, bool $forceDelete = false): int
    {
        if ($names === []) {
            return 0;
        }

        $query = $owner->attachedAttributes()->whereIn('name', $names);

        $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

        foreach ($names as $name) {
            AttributeDetached::dispatch($owner, $name);
        }

        return (int) $deleted;
    }
}
