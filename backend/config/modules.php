<?php

use App\Modules\Platform\ModuleServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Registered modules
    |--------------------------------------------------------------------------
    |
    | Every module lives in app/Modules/<Module> and is enabled by listing its
    | ModuleServiceProvider here. `php artisan make:module <Name>` appends to
    | this list automatically. Order matters only for route registration
    | inside a surface (earlier modules win on identical URIs).
    |
    */

    'modules' => [
        ModuleServiceProvider::class,
        App\Modules\Identity\ModuleServiceProvider::class,
        App\Modules\Localization\ModuleServiceProvider::class,
        App\Modules\Catalog\ModuleServiceProvider::class,
        App\Modules\Inventory\ModuleServiceProvider::class,
        App\Modules\Shipping\ModuleServiceProvider::class,
        App\Modules\Commerce\ModuleServiceProvider::class,
        App\Modules\AfterSales\ModuleServiceProvider::class,
        App\Modules\Billing\ModuleServiceProvider::class,
        App\Modules\Affiliates\ModuleServiceProvider::class,
        App\Modules\Storefront\ModuleServiceProvider::class,
        App\Modules\Content\ModuleServiceProvider::class,
        App\Modules\Engagement\ModuleServiceProvider::class,
        App\Modules\Notifications\ModuleServiceProvider::class,
        App\Modules\Integrations\ModuleServiceProvider::class,
        App\Modules\Ai\ModuleServiceProvider::class,
        App\Modules\Reporting\ModuleServiceProvider::class,
        App\Modules\Api\ModuleServiceProvider::class,
        App\Modules\Support\ModuleServiceProvider::class,
        // make:module inserts above this line
    ],

];
