<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Database\Factories\AttributeRevisionFactory;
use RoundlyConsulting\Attributes\Enums\RevisionType;

/**
 * @property int $id
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string $name
 * @property RevisionType $type
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $old_value_type
 * @property string|null $new_value_type
 * @property Collection<string, mixed>|null $old_meta
 * @property Collection<string, mixed>|null $new_meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class AttributeRevision extends Model
{
    /** @use HasFactory<AttributeRevisionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $table = config('attributes.history.table', 'attribute_revisions');

        return is_string($table) ? $table : 'attribute_revisions';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): AttributeRevisionFactory
    {
        return AttributeRevisionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RevisionType::class,
            'old_meta' => 'collection',
            'new_meta' => 'collection',
        ];
    }
}
