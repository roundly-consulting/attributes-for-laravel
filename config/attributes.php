<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;

return [

    /*
    |--------------------------------------------------------------------------
    | Attribute Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store attached attributes. Override this with
    | your own model (extending the package model) if you need to customise the
    | behaviour of stored attributes.
    |
    */

    'model' => Attribute::class,

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The database table used to store attributes. Change this if "attributes"
    | clashes with an existing table in your application.
    |
    */

    'table' => 'attributes',

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, attaching an attribute whose name is not registered in the
    | definitions below (or via the Attributes facade) throws an
    | UnknownAttributeException. Leave disabled for free-form key/value usage.
    |
    */

    'strict' => env('ATTRIBUTES_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Prune After Days
    |--------------------------------------------------------------------------
    |
    | The default age, in days, used by the attributes:prune command when no
    | --days option is supplied. Soft-deleted attributes older than this are
    | permanently removed.
    |
    */

    'prune_after_days' => env('ATTRIBUTES_PRUNE_AFTER_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | History / Audit Trail
    |--------------------------------------------------------------------------
    |
    | When enabled, every attach/sync/detach records an old -> new revision in
    | the attribute_revisions table, readable via $model->history(). Disabled
    | by default so there is no table cost unless you opt in. Encrypted values
    | are stored in their ciphertext form, so the audit log never leaks secrets.
    |
    */

    'history' => [
        'enabled' => env('ATTRIBUTES_HISTORY', false),
        'table' => 'attribute_revisions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute Definitions
    |--------------------------------------------------------------------------
    |
    | Register known attributes with their type and validation rules. Each key
    | is the attribute name; the value supports:
    |
    |   - type:      one of AttributeType's backed values (string, integer,
    |                float, boolean, array, datetime).
    |   - rules:     additional Laravel validation rules.
    |   - required:  bool; enforced by $model->validateAttributes().
    |   - default:   value returned by typed reads when the attribute is unset.
    |   - unique:    'owner' (unique per owner type), 'global' (unique across
    |                every owner), true (= owner), or false/'none' (default).
    |   - encrypted: bool; stores the value as ciphertext via Crypt at rest.
    |
    | Models may also declare their own definitions via a public
    | attributeDefinitions() method or a $attributeDefinitions array property;
    | those override same-named global definitions for that model only.
    |
    | Note: encrypted values cannot be matched by the whereAttribute* value
    | scopes (ciphertext is non-deterministic), and whereAttributeBetween on
    | the text column sorts lexicographically (reliable for ISO-8601 dates and
    | strings; integer ranges are zero-pad-sensitive).
    |
    |   'definitions' => [
    |       'rating' => ['type' => 'integer', 'rules' => ['min:1', 'max:5']],
    |       'sku'    => ['type' => 'string', 'unique' => 'global'],
    |       'token'  => ['type' => 'string', 'encrypted' => true],
    |       'retries'=> ['type' => 'integer', 'default' => 3],
    |   ],
    |
    */

    'definitions' => [],

];
