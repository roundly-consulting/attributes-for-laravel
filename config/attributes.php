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
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic owner column. Use "uuid" or "ulid"
    | when the models attributes attach to use UUID/ULID primary keys, otherwise
    | leave it as "bigint". An unrecognized value throws when the migrations run
    | rather than falling back. Your owner models must share one key type — set
    | this to match.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('ATTRIBUTES_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, writing an attribute (a value or its meta) whose name is not
    | registered in the definitions below, via the Attributes facade, or in the
    | owner model's own schema throws an UnknownAttributeException. Leave
    | disabled for free-form key/value usage. Accepts true/false, 1/0, on/off.
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
    | When enabled, every attach/sync/detach/meta change records an old -> new revision in
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
    |                float, boolean, array, datetime). Values are validated
    |                against it and stored in it ('5' -> 5 for an integer).
    |   - rules:     additional Laravel validation rules.
    |   - required:  bool; enforced by $model->validateAttributes().
    |   - default:   value returned by reads when the attribute is not attached.
    |   - unique:    'owner' (unique per owner type), 'global' (unique across
    |                every owner), true (= owner), or false/'none' (default).
    |                Backed by a unique index on a hash of the value — a keyed
    |                blind index for encrypted values, so it covers them too.
    |   - encrypted: bool; stores the value as ciphertext via Crypt at rest.
    |
    | Models may also declare their own definitions via a public
    | attributeDefinitions() method or a $attributeDefinitions array property;
    | those override same-named global definitions for that model only.
    |
    | Note: the query scopes compare typed values — numbers numerically,
    | datetimes chronologically (they are stored in UTC), strings as text.
    | Encrypted values cannot be matched, ranged or ordered by them (the
    | ciphertext is non-deterministic).
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
