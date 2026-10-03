<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing stored attributes from `attributes.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class AttributeModel
{
    /**
     * @return class-string<Attribute>
     */
    public static function class(): string
    {
        return ModelResolver::for('attributes.model', Attribute::class);
    }
}
