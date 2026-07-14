<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing stored attributes from `attributes.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not an Attribute (so it cannot answer the
 * package's casts, scopes, or typed reads) falls back to the packaged model.
 */
final class AttributeModel
{
    /**
     * @return class-string<Attribute>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('attributes.model', Attribute::class);

        return is_a($model, Attribute::class, true) ? $model : Attribute::class;
    }
}
