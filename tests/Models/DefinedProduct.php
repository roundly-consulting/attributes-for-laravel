<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

final class DefinedProduct extends Model implements HasAttributesContract
{
    use HasAttributes;

    protected $table = 'products';

    protected $guarded = [];

    /** @var array<string, array<string, mixed>> */
    protected array $attributeDefinitions = [
        'rating' => ['type' => 'integer', 'rules' => ['min:1', 'max:5'], 'required' => true],
        'sku' => ['type' => 'string', 'unique' => 'global'],
        'token' => ['type' => 'string', 'encrypted' => true],
        'retries' => ['type' => 'integer', 'default' => 3],
    ];
}
