<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

final class AttachAttributeAction
{
    public function __construct(
        private readonly AttributeRegistry $registry,
    ) {}

    /**
     * @param  Model&HasAttributes  $owner
     */
    public function execute(Model $owner, AttributeData $data): Attribute
    {
        $this->registry->assertKnown($data->name);
        $this->registry->validate($data->name, $data->value);

        $attribute = $owner->attachedAttributes()->updateOrCreate(
            ['name' => $data->name],
            ['value' => $data->value, 'meta' => $data->meta],
        );

        AttributeAttached::dispatch($owner, $attribute);

        return $attribute;
    }
}
