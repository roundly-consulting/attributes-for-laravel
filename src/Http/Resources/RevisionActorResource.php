<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Http\Resources;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Attributes\Models\AttributeRevision;

/**
 * Who made a revision, for an API: `{id, type}`, where `type` is the actor model's class
 * basename (`User`) — never a class name or a morph alias. AttributeRevisionResource renders
 * its `actor` through the class configured as `attributes.resources.revision_actor`, and
 * only for a revision with an actor.
 *
 * It wraps the {@see AttributeRevision}, not the actor model: history renders without
 * loading any actor (no query per row), and a deleted actor still renders. A host resource
 * swapped in via the config receives the same revision and reads `actor_type` / `actor_id`,
 * or the `actor` relation it eager-loaded.
 *
 * @property AttributeRevision $resource
 */
final class RevisionActorResource extends JsonResource
{
    public function __construct(AttributeRevision $revision)
    {
        parent::__construct($revision);
    }

    /**
     * @return array{id: int|string|null, type: string|null}
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource->actorType();

        return [
            'id' => $this->resource->actorId(),
            'type' => $type === null ? null : class_basename(Relation::getMorphedModel($type) ?? $type),
        ];
    }
}
