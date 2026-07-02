<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation
    |--------------------------------------------------------------------------
    |
    | The web installer runs until the lock file exists, then its routes
    | return 404 forever. `installed_override` short-circuits the check —
    | used by the test suite (MAGNA_INSTALLED=true) so application tests
    | run as an installed site.
    |
    */

    'installed_override' => env('MAGNA_INSTALLED'),

    'install' => [
        'lock_path' => storage_path('app/magna-installed.json'),
        'env_path' => base_path('.env'),
    ],

];
