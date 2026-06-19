<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

final class MalformedDefProduct extends Model implements HasAttributesContract
{
    use HasAttributes;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    public function attributeDefinitions(): array
    {
        return [
            'good' => ['type' => 'integer'],
            'bad' => 'not-an-array',
        ];
    }
}
