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

final readonly class AttachAttributesAction
{
    public function __construct(
        private AttributeRegistry $registry,
        private WriteAttributeAction $write,
    ) {}

    /**
     * Attach (or update) several attributes, all or nothing: every value is
     * validated before the first write, and the writes share one transaction.
     *
     * @param  Model&HasAttributes  $owner
     * @return Collection<int, Attribute>
     */
    public function execute(Model $owner, AttributeData ...$data): Collection
    {
        foreach ($data as $item) {
            $this->registry->assertWritable($owner, $item->name, $item->value);
        }

        return OwnerTransaction::run($owner, function () use ($owner, $data): Collection {
            /** @var Collection<int, Attribute> $attributes */
            $attributes = new Collection;

            foreach ($data as $item) {
                $attributes->push($this->write->execute($owner, $item));
            }

            return $attributes;
        });
    }
}
