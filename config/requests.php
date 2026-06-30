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
    | Enforce status transitions
    |--------------------------------------------------------------------------
    |
    | When enabled, the lifecycle graph is enforced and illegal moves (for
    | example re-approving a rejected request) throw InvalidStatusTransition.
    | Defaults to false to preserve the original toggle behaviour; turn it on
    | for a stricter, guarded workflow.
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
    | Null means requests never expire unless an expiry is provided explicitly.
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
