<?php

use App\Models\Administrator;
use App\Models\Receptionist;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'administrators'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'administrators',
        ],

        'receptionist' => [
            'driver' => 'session',
            'provider' => 'receptionists',
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => 'administrators',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'administrators' => [
            'driver' => 'eloquent',
            'model' => Administrator::class,
        ],

        'receptionists' => [
            'driver' => 'eloquent',
            'model' => Receptionist::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'administrators' => [
            'provider' => 'administrators',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
