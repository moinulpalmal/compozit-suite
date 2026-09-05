<?php

use App\Models\Admin\AuditLog;
use OwenIt\Auditing\Resolvers\IpAddressResolver;
use OwenIt\Auditing\Resolvers\UrlResolver;
use OwenIt\Auditing\Resolvers\UserAgentResolver;
use OwenIt\Auditing\Resolvers\UserResolver;

/*
|--------------------------------------------------------------------------
| Audit trail
|--------------------------------------------------------------------------
|
| `owen-it/laravel-auditing`, pointed at this application's own names.
| ARCHITECTURE.md §9.3 is the decision record; this file is only the wiring.
|
| **`mergeConfigFrom` is shallow.** A top-level key declared here *replaces*
| the package's array rather than merging into it, so `user` and `resolvers`
| have to name every sub-key even where the value matches the package default.
|
*/

return [

    'enabled' => env('AUDITING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Implementation
    |--------------------------------------------------------------------------
    |
    | The application's own model, not the package's. ARCHITECTURE.md §5 names
    | `Admin\AuditLog` and §6.3 names the table, and both predate this package —
    | so the package is renamed to fit the map rather than the other way round.
    |
    */

    'implementation' => AuditLog::class,

    /*
    |--------------------------------------------------------------------------
    | User Morph prefix & Guards
    |--------------------------------------------------------------------------
    |
    | `api` is dropped from the package's default pair: this application has no
    | API guard, and consulting one that does not exist can only ever resolve a
    | null actor more slowly.
    |
    */

    'user' => [
        'morph_prefix' => 'user',
        'guards' => [
            'web',
        ],
        'resolver' => UserResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Resolvers
    |--------------------------------------------------------------------------
    |
    | All three are the package's own. `maccounts` swaps the IP resolver for one
    | that renders `::1` as `127.0.0.1`; that is cosmetic and would cost a new
    | `app/Resolvers/` directory, which CLAUDE.md §4 does not allow without
    | asking. If it is ever wanted, it is a four-line class.
    |
    */

    'resolvers' => [
        'ip_address' => IpAddressResolver::class,
        'user_agent' => UserAgentResolver::class,
        'url' => UrlResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Events
    |--------------------------------------------------------------------------
    |
    | The package also supports `retrieved`. It stays off: read auditing writes
    | a row per model per page render, which on a paginated list is a hundred
    | inserts to record that somebody looked at a screen.
    |
    | These four strings are a contract — `App\Enums\Admin\AuditEvent` mirrors
    | them exactly, because the package writes the raw string and the filter
    | dropdown has to match what is in the column.
    |
    */

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | Off, which has one consequence worth stating loudly: **a model's `$hidden`
    | list is NOT honoured.** Only strict mode excludes hidden attributes, so
    | `User` carries an explicit `$auditExclude` for `password` and
    | `remember_token`. Without it the password hash lands in the trail.
    |
    */

    'strict' => false,

    /*
    |--------------------------------------------------------------------------
    | Global exclude
    |--------------------------------------------------------------------------
    |
    | Empty on purpose. A model's `$auditExclude` *replaces* this list rather
    | than merging with it, so anything put here would be silently dropped for
    | every model that declares its own — which is every model with a secret.
    | Exclusions therefore live on the models, where they cannot be cancelled.
    |
    */

    'exclude' => [],

    /*
    |--------------------------------------------------------------------------
    | Empty Values
    |--------------------------------------------------------------------------
    |
    | Package defaults.
    |
    */

    'empty_values' => true,
    'allowed_empty_values' => [
        'retrieved',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Array Values
    |--------------------------------------------------------------------------
    |
    | Package default, and it does **not** protect the JSON columns. The package
    | reads `$model->attributes`, which holds the raw encoded *string*, so a
    | `json`-cast column is never an array by the time this check runs. The six
    | payload columns are excluded per-model instead — see `Concerns\Audited`.
    |
    */

    'allowed_array_values' => false,

    /*
    |--------------------------------------------------------------------------
    | Audit Timestamps
    |--------------------------------------------------------------------------
    |
    | Off: `created_at`, `updated_at` and `deleted_at` are excluded from every
    | diff. The audit row carries its own `created_at`, so recording that a
    | model's `updated_at` changed on an update says nothing.
    |
    */

    'timestamps' => false,

    /*
    |--------------------------------------------------------------------------
    | Audit Threshold
    |--------------------------------------------------------------------------
    |
    | Zero — audits are kept forever, and nothing prunes them. This is a
    | decision rather than a default, and its cost is that `audit_logs` grows
    | without bound; ARCHITECTURE.md §9.3 records why that was accepted and what
    | keeps the surface fast regardless.
    |
    */

    'threshold' => 0,

    /*
    |--------------------------------------------------------------------------
    | Audit Driver
    |--------------------------------------------------------------------------
    */

    'driver' => 'database',

    'drivers' => [
        'database' => [
            'table' => 'audit_logs',
            'connection' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Queue Configurations
    |--------------------------------------------------------------------------
    |
    | Off, and not merely by default. `QUEUE_CONNECTION` is `database`, so
    | queueing an audit writes a `jobs` row in order to later write an
    | `audit_logs` row — two writes where there was one. It would also make the
    | `deploy/` queue worker load-bearing for the trail's *completeness*, and a
    | worker that is down would lose audits with nothing to show for it.
    |
    */

    'queue' => [
        'enable' => false,
        'connection' => 'sync',
        'queue' => 'default',
        'delay' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Console
    |--------------------------------------------------------------------------
    |
    | Console writes — seeders, migrations, artisan commands, queued jobs — are
    | not audited. This is the same posture `ActorObserver` takes for
    | `inserted_by` (ARCHITECTURE.md §9.3): with no authenticated actor there is
    | nobody to record, and a trail full of "System" rows from a re-seed buries
    | the rows a person made. Note the package does not merely skip the write —
    | `isAuditingEnabled()` returns false, so the observer is never attached.
    |
    */

    'console' => false,
];
