<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\OwnerTransaction;

final readonly class SyncAttributeMetaAction
{
    public function __construct(
        private AttributeRegistry $registry,
        private WriteAttributeAction $write,
    ) {}

    /**
     * Replace one attribute's meta (null clears it), attaching a null-valued
     * attribute when it is not attached yet. It goes through the same write as
     * a value — strict mode, history and AttributeAttached included. The value is
     * left as it is (or null for a new attribute, which every rule accepts), so
     * strict mode is the only check it can fail.
     *
     * @param  Model&HasAttributes  $owner
     * @param  Collection<string, mixed>|null  $meta
     */
    public function execute(Model $owner, string $name, ?Collection $meta): Attribute
    {
        $this->registry->assertKnown($name, $owner);

        return OwnerTransaction::run(
            $owner,
            fn (): Attribute => $this->write->execute($owner, new AttributeData($name, null, $meta), metaOnly: true),
        );
    }
}
