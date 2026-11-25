<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branch "serves zones" moves from cities (decision #53, interim) to real shipping zones
 * (decision #65). Each old city link is mapped onto the shipping zones that list that
 * city, then the city table is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_shipping_zones', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->primary(['branch_id', 'shipping_zone_id']);
        });

        if (Schema::hasTable('branch_zones')) {
            $rows = DB::table('branch_zones')
                ->join('shipping_zone_cities', 'shipping_zone_cities.city_id', '=', 'branch_zones.city_id')
                ->select('branch_zones.branch_id', 'shipping_zone_cities.shipping_zone_id')
                ->distinct()
                ->get()
                ->map(static fn (object $row): array => ['branch_id' => $row->branch_id, 'shipping_zone_id' => $row->shipping_zone_id])
                ->all();

            if ($rows !== []) {
                DB::table('branch_shipping_zones')->insertOrIgnore($rows);
            }

            Schema::drop('branch_zones');
        }
    }

    public function down(): void
    {
        Schema::create('branch_zones', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->primary(['branch_id', 'city_id']);
        });

        Schema::dropIfExists('branch_shipping_zones');
    }
};
