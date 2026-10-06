<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

final class SoftDeletingProduct extends Model implements HasAttributesContract
{
    use HasAttributes;
    use SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];
}
