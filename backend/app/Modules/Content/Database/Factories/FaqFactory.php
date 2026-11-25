<?php

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => FaqGroup::General,
            'question' => ['en' => 'Can I use my own domain?', 'ar' => 'هل يمكنني استخدام نطاقي الخاص؟'],
            'answer' => ['en' => '<p>Yes, connect it from your store settings.</p>', 'ar' => '<p>نعم، اربطه من إعدادات متجرك.</p>'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
