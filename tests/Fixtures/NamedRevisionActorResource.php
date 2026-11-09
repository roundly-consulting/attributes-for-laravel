<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Tests\Models\User;

/**
 * A host's own revision-actor resource: adds the user's name.
 *
 * @property AttributeRevision $resource
 */
final class NamedRevisionActorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $this->resource->actor;

        return [
            'id' => $this->resource->actorId(),
            'name' => $actor instanceof User ? $actor->name : null,
        ];
    }
}
