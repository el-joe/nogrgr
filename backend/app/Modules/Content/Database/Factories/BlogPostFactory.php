<?php

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'store-guide-'.Str::lower(Str::random(8)),
            'title' => ['en' => 'How to price your first products', 'ar' => 'كيف تسعّر منتجاتك الأولى'],
            'excerpt' => ['en' => 'A simple method to set prices that cover costs and stay competitive.', 'ar' => 'طريقة بسيطة لتحديد أسعار تغطي التكاليف وتبقى منافسة.'],
            'body' => ['en' => '<p>Start from your full cost, then add a clear margin.</p>', 'ar' => '<p>ابدأ من التكلفة الكاملة ثم أضف هامش ربح واضحًا.</p>'],
            'author_name' => 'Nogrgr team',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'reading_minutes' => 3,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PostStatus::Draft, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => PostStatus::Published, 'published_at' => now()->addWeek()]);
    }
}
