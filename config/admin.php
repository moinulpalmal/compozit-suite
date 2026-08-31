<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap super administrator
    |--------------------------------------------------------------------------
    |
    | The account `php artisan admin:create-super` provisions on a freshly
    | migrated database. A production deploy seeds the RBAC catalogue and the
    | designations but no users at all, so without this there is nobody to log
    | in as — and the login identifier is `employee_id`, not email
    | (ARCHITECTURE.md §9.6).
    |
    | These are read from the environment through config rather than `env()` at
    | the point of use, because production runs `config:cache` and `env()` then
    | returns null everywhere outside this directory.
    |
    | Leave them unset in local development: `DatabaseSeeder` already creates a
    | test user there. The command refuses to run rather than inventing a
    | password if `ADMIN_PASSWORD` is missing.
    |
    */

    'super' => [
        'employee_id' => env('ADMIN_EMPLOYEE_ID'),
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),

        /*
         * Matched against `designations.name`. Optional — `users.designation_id`
         * is nullable, and an admin can set it through the UI afterwards.
         */
        'designation' => env('ADMIN_DESIGNATION'),
    ],

];
