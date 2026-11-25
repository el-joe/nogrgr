<?php

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'shipping-policy-'.Str::lower(Str::random(6)),
            'title' => ['en' => 'Shipping policy', 'ar' => 'سياسة الشحن'],
            'body' => ['en' => '<p>Orders ship within two business days.</p>', 'ar' => '<p>تُشحن الطلبات خلال يومي عمل.</p>'],
            'target' => PageTarget::Central,
            'is_compliance' => false,
            'is_system' => false,
            'status' => PageStatus::Active,
        ];
    }
}
