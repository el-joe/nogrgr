<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central website content (P6): blog categories / posts, static pages, FAQs,
 * newsletter subscribers (double opt-in) and contact-form messages.
 * Translatable columns are JSON (spatie/laravel-translatable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('slug', 160)->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('author_name', 120)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_webp_path')->nullable();
            $table->string('cover_avif_path')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->json('title');
            $table->json('body');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('target', 16)->default('central')->index();
            $table->boolean('is_compliance')->default(false);
            $table->boolean('is_system')->default(false);
            $table->string('status', 16)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 32)->default('general')->index();
            $table->json('question');
            $table->json('answer');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 8)->default('en');
            $table->string('status', 16)->default('pending')->index();
            $table->char('token', 64)->unique();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('subject', 160);
            $table->text('message');
            $table->string('locale', 8)->default('en');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
