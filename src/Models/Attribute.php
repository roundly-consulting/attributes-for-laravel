<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Database\Factories\AttributeFactory;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $name
 * @property string|null $value
 * @property Collection<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
        ];
    }
}
