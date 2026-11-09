<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Support\AttributesConfig;

/**
 * One revision for an API. The owner is left out (a history is listed per owner), and the
 * actor renders through the configured `attributes.resources.revision_actor` class — so no
 * class name or morph alias goes over the wire. Values are the stored text: an encrypted
 * attribute's revision carries its ciphertext, never the plain value.
 *
 * @property AttributeRevision $resource
 */
final class AttributeRevisionResource extends JsonResource
{
    public function __construct(AttributeRevision $revision)
    {
        parent::__construct($revision);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $revision = $this->resource;

        return [
            'id' => $revision->getKey(),
            'name' => $revision->name,
            'type' => $revision->type->value,
            'old_value' => $revision->old_value,
            'new_value' => $revision->new_value,
            'old_value_type' => $revision->old_value_type,
            'new_value_type' => $revision->new_value_type,
            'old_meta' => $revision->old_meta?->all(),
            'new_meta' => $revision->new_meta?->all(),
            'actor' => $this->actor($request),
            'created_at' => $revision->created_at?->toJSON(),
        ];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function actor(Request $request): ?array
    {
        if ($this->resource->actorType() === null) {
            return null;
        }

        $resource = AttributesConfig::revisionActorResource();

        return new $resource($this->resource)->resolve($request);
    }
}
