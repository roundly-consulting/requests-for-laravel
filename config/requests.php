<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Models\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Approval engine
    |--------------------------------------------------------------------------
    |
    | Request approvals run on roundly-consulting/approvals-for-laravel. The
    | approval mechanics — rules (unanimous / quorum / any / weighted), staged
    | pipelines, delegation, expiry, and named workflow presets — are configured
    | in config/approvals.php. Publish it with:
    |
    |     php artisan vendor:publish --tag="approvals-config"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Request model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used when creating requests. Override this with your
    | own subclass of the package model to add behaviour or relationships.
    |
    */

    'model' => Request::class,

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic author column. Use "uuid" or "ulid"
    | when the models that column points at use UUID/ULID primary keys, otherwise
    | leave it as "bigint". Your morph targets must share one key type; set this
    | to match. Any other value throws an InvalidConfigurationException when
    | the migration runs.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('REQUESTS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The key type of the requests table's own id: "bigint" (the Laravel
    | default), "uuid" or "ulid" — uuid/ulid ids are generated when a request
    | is created. Anything else throws an InvalidConfigurationException.
    |
    | The approvals engine's polymorphic columns (a round's subject, a
    | decision's approvable) point at this id and use approvals.key_type, so
    | set this to the same value as APPROVALS_KEY_TYPE: with UUID-keyed
    | approvers (APPROVALS_KEY_TYPE=uuid) a bigint request id cannot be stored
    | there on PostgreSQL. This is a different axis from "key_type" above (the
    | key type of the authors a request points at).
    |
    | It is fixed when the migration first runs, so choose it before migrating.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'primary_key_type' => env('REQUESTS_PRIMARY_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Enforce status transitions
    |--------------------------------------------------------------------------
    |
    | When enabled, the lifecycle graph is enforced and illegal moves (for
    | example re-approving a rejected request) throw InvalidStatusTransition.
    | Defaults to false to preserve the original toggle behaviour; turn it on
    | for a stricter, guarded workflow. Env strings ("true"/"1"/"on"/"yes",
    | "false"/"0"/"off"/"no") work; a blank value is not set (so false), and
    | anything else throws an InvalidConfigurationException.
    |
    */

    'enforce_transitions' => false,

    /*
    |--------------------------------------------------------------------------
    | Default time to live
    |--------------------------------------------------------------------------
    |
    | When set (in minutes) and a request is created without an explicit expiry,
    | the request is stamped with an expires_at of now() plus this many minutes.
    | Null (or blank) means requests never expire unless an expiry is provided
    | explicitly.
    | A numeric string (an env() value) works too; anything but a positive
    | whole number throws an InvalidConfigurationException.
    |
    */

    'default_ttl' => null,

    /*
    |--------------------------------------------------------------------------
    | Register the Requests facade alias
    |--------------------------------------------------------------------------
    |
    | When true, the package registers a short "Requests" class alias for the
    | facade. The alias is skipped automatically if the host application has
    | already aliased that name, so it is collision-safe. The fully-qualified
    | facade (RoundlyConsulting\Requests\Facades\Requests) always works.
    |
    */

    'register_facade_alias' => true,

];
