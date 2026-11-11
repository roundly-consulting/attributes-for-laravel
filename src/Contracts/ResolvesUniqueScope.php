<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the scope model a `unique => scope` value is unique within — a team, a
 * tenant — from the attribute's owner. Name it as a definition's `unique_scope` or
 * as `attributes.unique_scope`; it is resolved from the container.
 *
 * Return a saved model, or a non-empty int/string key. Null (or anything else) is
 * refused: an owner without a scope cannot be checked, so the write throws.
 */
interface ResolvesUniqueScope
{
    public function __invoke(Model $owner): Model|int|string|null;
}
