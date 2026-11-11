<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Http\Resources\RevisionActorResource;
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
    | Actor Key Type
    |--------------------------------------------------------------------------
    |
    | The key type of the polymorphic `actor` column on revisions (who made a
    | change — usually your User model, which may use another key type than
    | the models attributes attach to). Set it before running the migrations.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'actor_key_type' => env('ATTRIBUTES_ACTOR_KEY_TYPE', 'bigint'),

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
    | Delete With Owner
    |--------------------------------------------------------------------------
    |
    | Permanently deleting an owner (a model without SoftDeletes, or
    | forceDelete()) removes its attribute rows too, freeing its unique
    | values. Soft-deleting the owner keeps them. Recorded revisions are kept
    | either way, as the audit trail. Accepts true/false, 1/0, on/off.
    |
    */

    'delete_with_owner' => env('ATTRIBUTES_DELETE_WITH_OWNER', true),

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

        // Who made each change. Off by default: it needs the actor columns the
        // `update_attribute_revisions_add_actor` migration adds, so publish and
        // run it first. The actor is the one named with
        // Attributes::for($model)->by($user), else the one a resolver set with
        // Attributes::resolveActorUsing() returns, else the authenticated user
        // of `guard` (null = the default guard). Accepts true/false, 1/0, on/off.
        'actor' => [
            'enabled' => env('ATTRIBUTES_HISTORY_ACTOR', false),
            'guard' => env('ATTRIBUTES_HISTORY_ACTOR_GUARD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Whole-Set Validation
    |--------------------------------------------------------------------------
    |
    | Off: each value is validated against its own definition alone. On: every
    | value write (set, setMany, sync, staged saves) and validateAttributes()
    | validate the owner's whole attribute set after the change in one pass,
    | so rules may refer to other attributes (`required_if:type,physical`,
    | `gte:min_price`). Attributes::for($model)->setValidated() always does.
    | Accepts true/false, 1/0, on/off.
    |
    */

    'validation' => [
        'whole_set' => env('ATTRIBUTES_VALIDATE_WHOLE_SET', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Resources
    |--------------------------------------------------------------------------
    |
    | `revision_actor` renders who made a revision (the `actor` of
    | AttributeRevisionResource). The default gives `{id, type}`, where `type`
    | is the actor model's class basename — never a class name. Point it at a
    | JsonResource subclass of your own to add a name or an avatar; it
    | receives the AttributeRevision (`actor_type`, `actor_id`, and the `actor`
    | relation). Null = the default.
    |
    */

    'resources' => [
        'revision_actor' => RevisionActorResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unique Scope
    |--------------------------------------------------------------------------
    |
    | What a `'unique' => 'scope'` definition without its own `unique_scope` is
    | unique within — a team or tenant the owner belongs to. Either an
    | attribute or relation name on the owner ('team_id', 'team') or the
    | class-string of an invokable implementing
    | RoundlyConsulting\Attributes\Contracts\ResolvesUniqueScope. An owner that
    | resolves no scope is refused rather than checked against a shared pool.
    | Null = no default (a scoped definition must then name its own).
    |
    */

    'unique_scope' => null,

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
    |   - unique:    'owner' (unique per owner type, across every owner of that
    |                type), 'scope' (unique per owner type within the scope
    |                model the owner resolves — see unique_scope), 'global'
    |                (unique across every owner), true (= owner), or
    |                false/'none' (default). Backed by a unique index on a hash
    |                of the value — a keyed blind index for encrypted values, so
    |                it covers them too.
    |   - unique_scope: for 'unique' => 'scope' only — an attribute or relation
    |                name on the owner ('team_id', 'team') or a
    |                ResolvesUniqueScope class-string. Defaults to the
    |                `unique_scope` setting above.
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
    |       'code'   => ['type' => 'string', 'unique' => 'scope', 'unique_scope' => 'team_id'],
    |       'token'  => ['type' => 'string', 'encrypted' => true],
    |       'retries'=> ['type' => 'integer', 'default' => 3],
    |   ],
    |
    */

    'definitions' => [],

];
