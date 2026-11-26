<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Appearance (VEN-034d/e/f, tenant DB): the store's own menu links — store bar above the
| theme header ("header") or a footer column ("footer") — and its social profiles shown
| in every storefront footer.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_menu_links', function (Blueprint $table): void {
            $table->id();
            $table->string('placement', 16)->index();
            $table->string('footer_column', 16)->nullable();
            $table->json('label');
            $table->string('url', 500);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['placement', 'sort_order']);
        });

        Schema::create('store_social_links', function (Blueprint $table): void {
            $table->id();
            $table->string('network', 20)->unique();
            $table->string('url', 500);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_social_links');
        Schema::dropIfExists('store_menu_links');
    }
};
