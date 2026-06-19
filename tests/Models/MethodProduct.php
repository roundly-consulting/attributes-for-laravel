<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

final class MethodProduct extends Model implements HasAttributesContract
{
    use HasAttributes;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function attributeDefinitions(): array
    {
        return [
            'rating' => ['type' => 'integer', 'rules' => ['min:1', 'max:10'], 'required' => true],
            'level' => ['type' => 'integer', 'default' => 7],
        ];
    }
}
