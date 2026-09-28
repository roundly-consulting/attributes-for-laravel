<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\OwnerTransaction;

final readonly class AttachAttributeAction
{
    public function __construct(
        private AttributeRegistry $registry,
        private WriteAttributeAction $write,
    ) {}

    /**
     * Attach (or update) one attribute: validated against its definition (strict
     * mode, rules, unique scope), stored in the definition's type, meta kept
     * unless new meta is given.
     *
     * @param  Model&HasAttributes  $owner
     */
    public function execute(Model $owner, AttributeData $data): Attribute
    {
        $this->registry->assertWritable($owner, $data->name, $data->value);

        return OwnerTransaction::run($owner, fn (): Attribute => $this->write->execute($owner, $data));
    }
}
