<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Custom Twig themes (VEN-036 / ADM-089, decision #121). Central because the platform
| review queue lists versions across stores; both tables are tenant-scoped
| (BelongsToCurrentTenant). One custom theme per store, many uploaded versions; the
| extracted package lives on the private `custom-themes` disk under {tenant}/{version}.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_themes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('live_version_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('last_render_error_at')->nullable();
            $table->string('last_render_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('custom_theme_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('custom_theme_id')->constrained('custom_themes')->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('status', 24)->index();
            $table->string('name', 120);
            $table->json('manifest')->nullable();
            $table->json('report');
            $table->char('checksum', 64);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->string('original_filename', 190)->nullable();
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->string('uploaded_by_name', 190)->nullable();
            $table->text('submit_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->string('reviewed_by_name', 190)->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'custom_theme_id']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_theme_versions');
        Schema::dropIfExists('custom_themes');
    }
};
