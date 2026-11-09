<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\OwnerTransaction;

/**
 * @internal the whole-set write behind AttachValidatedAttributesAction and, with
 * `attributes.validation.whole_set` on, behind the attach and sync actions
 */
final readonly class WriteAttributeSetAction
{
    public function __construct(
        private AttributeRegistry $registry,
        private WriteAttributeAction $write,
    ) {}

    /**
     * Write several attributes validated as one set, all or nothing. Inside one
     * transaction, with the owner's row locked: the owner's stored values overlaid by
     * `$data` — or, with `$replace` (a sync), `$data` alone — are validated in a single
     * pass (strict mode, every definition's rules, the written values' unique scopes),
     * `$before` runs (a sync's detach), then every value is written. Two whole-set
     * writes of one owner never both validate against the set the other changes.
     *
     * @param  Model&HasAttributes  $owner
     * @param  list<AttributeData>  $data
     * @param  (callable(): void)|null  $before
     * @return Collection<int, Attribute>
     */
    public function execute(Model $owner, array $data, bool $replace = false, ?callable $before = null): Collection
    {
        if ($data !== [] && (! $owner->exists || $owner->getKey() === null)) {
            throw AttributesException::unsavedOwner($data[0]->name, $owner);
        }

        $values = [];

        foreach ($data as $item) {
            $values[$item->name] = $item->value;
        }

        return OwnerTransaction::runLocked($owner, function () use ($owner, $data, $values, $replace, $before): Collection {
            $this->registry->assertWritableSet($owner, $values, $replace);

            if ($before !== null) {
                $before();
            }

            /** @var Collection<int, Attribute> $attributes */
            $attributes = new Collection;

            foreach ($data as $item) {
                $attributes->push($this->write->execute($owner, $item));
            }

            return $attributes;
        });
    }
}
