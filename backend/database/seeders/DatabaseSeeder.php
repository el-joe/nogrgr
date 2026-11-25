<?php

namespace Database\Seeders;

use App\Modules\Content\Database\Seeders\ContentStarterSeeder;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Localization\Database\Seeders\CitySeeder;
use App\Modules\Localization\Database\Seeders\CountrySeeder;
use App\Modules\Localization\Database\Seeders\CurrencySeeder;
use App\Modules\Localization\Database\Seeders\LanguageSeeder;
use Illuminate\Database\Seeder;

/**
 * Central database seeder. Tenant databases are seeded by TenantDatabaseSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CentralPermissionSeeder::class,
            CountrySeeder::class,
            CitySeeder::class,
            CurrencySeeder::class,
            LanguageSeeder::class,
            ContentStarterSeeder::class,
        ]);
    }
}
