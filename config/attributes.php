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
    | Attribute Definitions
    |--------------------------------------------------------------------------
    |
    | Register known attributes with their type and validation rules. Each key
    | is the attribute name; the value supports: type (one of AttributeType's
    | backed values), rules (Laravel validation rules), required (bool), and
    | default. Values are validated against these when attached.
    |
    |   'definitions' => [
    |       'rating' => ['type' => 'integer', 'rules' => ['min:1', 'max:5']],
    |       'color'  => ['type' => 'string', 'rules' => ['max:32']],
    |   ],
    |
    */

    'definitions' => [],

];
