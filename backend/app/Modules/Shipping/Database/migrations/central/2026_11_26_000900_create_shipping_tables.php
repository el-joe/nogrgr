<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central shipping configuration (P4, decision #63):
 *  - shipping_fixed_costs: one price_per_gram (USD) per country → products' fixed_shipping_costs map;
 *  - shipping_rules → countries → zones (cities) → weight bands {min g, max g, price minor};
 *  - shipping_companies + day rules per country {min, max days} + default company per country;
 *  - shipping_stop_periods (holidays / weekly off days, per company and/or country, null = all);
 *  - delivery_popup_days: days 1–10 with a % per country (country_key 0 = all countries);
 *  - shipping_settings: key → JSON value (default days, cut-off, weekly off days, toggles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_fixed_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->unique()->constrained('countries')->cascadeOnDelete();
            $table->decimal('price_per_gram', 14, 6);
            $table->timestamps();
        });

        Schema::create('shipping_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('shipping_rule_countries', function (Blueprint $table): void {
            $table->foreignId('shipping_rule_id')->constrained('shipping_rules')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['shipping_rule_id', 'country_id']);
            $table->index('country_id');
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_rule_id')->constrained('shipping_rules')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_zone_cities', function (Blueprint $table): void {
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->primary(['shipping_zone_id', 'city_id']);
            $table->index('city_id');
        });

        Schema::create('shipping_zone_bands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('min_weight_grams')->default(0);
            $table->unsignedInteger('max_weight_grams')->nullable()->comment('null = no upper bound');
            $table->unsignedBigInteger('price')->default(0)->comment('USD minor units');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('logo_path')->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('tracking_url_pattern', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('shipping_day_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_company_id')->constrained('shipping_companies')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_days');
            $table->unsignedSmallInteger('max_days');
            $table->timestamps();

            $table->unique(['shipping_company_id', 'country_id']);
        });

        Schema::create('shipping_default_companies', function (Blueprint $table): void {
            $table->foreignId('country_id')->primary()->constrained('countries')->cascadeOnDelete();
            $table->foreignId('shipping_company_id')->constrained('shipping_companies')->cascadeOnDelete();
        });

        Schema::create('shipping_stop_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('shipping_company_id')->nullable()->constrained('shipping_companies')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->cascadeOnDelete();
            $table->string('recurrence', 16)->default('once');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable()->comment('0 = Sunday … 6 = Saturday (weekly)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::create('delivery_popup_days', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('country_key')->default(0)->comment('country id, 0 = all countries');
            $table->unsignedTinyInteger('day_number');
            $table->unsignedTinyInteger('percentage')->default(0);
            $table->timestamps();

            $table->unique(['country_key', 'day_number']);
        });

        Schema::create('shipping_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_settings');
        Schema::dropIfExists('delivery_popup_days');
        Schema::dropIfExists('shipping_stop_periods');
        Schema::dropIfExists('shipping_default_companies');
        Schema::dropIfExists('shipping_day_rules');
        Schema::dropIfExists('shipping_companies');
        Schema::dropIfExists('shipping_zone_bands');
        Schema::dropIfExists('shipping_zone_cities');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_rule_countries');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_fixed_costs');
    }
};
