<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Models\VendorStaff;

/*
|--------------------------------------------------------------------------
| Guards (docs/plan/02-architecture.md)
|--------------------------------------------------------------------------
| admin     central admins        (central DB)  {central}/admin
| affiliate central affiliates    (central DB)  {central}/affiliate
| vendor    tenant staff          (tenant DB)   {store}/admin
| customer  tenant customers web  (tenant DB)   {store}/
| sanctum   customer tokens       (tenant DB)   /api/mobile/v1 (X-Tenant)
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        'affiliate' => [
            'driver' => 'session',
            'provider' => 'affiliates',
        ],

        'vendor' => [
            'driver' => 'session',
            'provider' => 'vendor_staff',
        ],

        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],

        'owner' => [
            'driver' => 'session',
            'provider' => 'owners',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],

        'affiliates' => [
            'driver' => 'eloquent',
            'model' => Affiliate::class,
        ],

        'vendor_staff' => [
            'driver' => 'eloquent',
            'model' => VendorStaff::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],

        'owners' => [
            'driver' => 'eloquent',
            'model' => Owner::class,
        ],
    ],

    /*
     * password_reset_tokens exists in the central DB (admins) and in every tenant DB
     * (vendor staff, customers); the default connection decides. Affiliates use
     * their own central table (an admin and an affiliate may share an email).
     */
    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'affiliates' => [
            'provider' => 'affiliates',
            'table' => 'affiliate_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'vendor_staff' => [
            'provider' => 'vendor_staff',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'customers' => [
            'provider' => 'customers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
