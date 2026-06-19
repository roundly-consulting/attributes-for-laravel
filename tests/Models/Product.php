<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

final class Product extends Model
{
    use HasAttributes;

    protected $guarded = [];
}
