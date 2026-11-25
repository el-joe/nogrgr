<?php

namespace App\Modules\Storefront\Database\Seeders;

use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Models\ThemeCategory;
use Illuminate\Database\Seeder;

/**
 * The 13 storefront themes (Figma: Ecommet 1:1002, Elora 1:15432 + 5 variants,
 * Souqify 1:72835 + 5 variants) and the 8 gallery categories. Idempotent (by slug).
 */
class ThemeCatalogSeeder extends Seeder
{
    /**
     * slug => [en, ar, sort].
     *
     * @var array<string, array{0: string, 1: string, 2: int}>
     */
    private const CATEGORIES = [
        'fashion' => ['Fashion', 'الأزياء', 1],
        'beauty' => ['Beauty', 'التجميل', 2],
        'fitness' => ['Fitness', 'اللياقة', 3],
        'furniture' => ['Furniture', 'الأثاث', 4],
        'grocery' => ['Grocery', 'البقالة', 5],
        'electronics' => ['Electronics', 'الإلكترونيات', 6],
        'pets' => ['Pets', 'الحيوانات الأليفة', 7],
        'kids' => ['Kids', 'الأطفال', 8],
    ];

    /**
     * slug => [family, variant, en name, ar name, en description, ar description, categories].
     *
     * @var list<array{0: string, 1: ThemeFamily, 2: int, 3: string, 4: string, 5: string, 6: string, 7: list<string>}>
     */
    private const THEMES = [
        ['ecommet', ThemeFamily::Ecommet, 1, 'Ecommet', 'إيكوميت', 'A clean, conversion-focused layout for general stores with big category tiles and best-seller rails.', 'تصميم نظيف يركز على المبيعات للمتاجر العامة مع مربعات أقسام كبيرة وشرائط الأكثر مبيعًا.', ['electronics', 'grocery', 'furniture']],
        ['elora-1', ThemeFamily::Elora, 1, 'Elora Essence', 'إيلورا إسنس', 'Elegant editorial look with a full-width hero and the signature card-fan best-seller slider.', 'مظهر أنيق بأسلوب المجلات مع واجهة عريضة وشريط الأكثر مبيعًا على شكل مروحة بطاقات.', ['fashion', 'beauty']],
        ['elora-2', ThemeFamily::Elora, 2, 'Elora Bloom', 'إيلورا بلوم', 'Soft colours and rounded cards made for beauty, skincare and gift shops.', 'ألوان هادئة وبطاقات دائرية الحواف لمتاجر التجميل والعناية بالبشرة والهدايا.', ['beauty', 'kids']],
        ['elora-3', ThemeFamily::Elora, 3, 'Elora Noir', 'إيلورا نوار', 'A bold dark palette that puts premium fashion and accessories in the spotlight.', 'لوحة ألوان داكنة وجريئة تبرز الأزياء والإكسسوارات الفاخرة.', ['fashion', 'electronics']],
        ['elora-4', ThemeFamily::Elora, 4, 'Elora Coral', 'إيلورا كورال', 'Warm accents, featured marquee strip and lifestyle banners for home and decor.', 'لمسات دافئة وشريط متحرك مميز ولافتات أسلوب حياة لمتاجر المنزل والديكور.', ['furniture', 'fashion']],
        ['elora-5', ThemeFamily::Elora, 5, 'Elora Breeze', 'إيلورا بريز', 'Light, airy sections for sportswear, fitness gear and outdoor products.', 'أقسام خفيفة ومنعشة للملابس الرياضية ومعدات اللياقة ومنتجات الهواء الطلق.', ['fitness', 'fashion']],
        ['elora-6', ThemeFamily::Elora, 6, 'Elora Velvet', 'إيلورا فيلفيت', 'Rich typography and large imagery for perfumes, jewellery and luxury goods.', 'خطوط فاخرة وصور كبيرة للعطور والمجوهرات والسلع الراقية.', ['beauty', 'fashion']],
        ['souqify-1', ThemeFamily::Souqify, 1, 'Souqify Market', 'سوقيفاي ماركت', 'A busy marketplace layout with deal cards, flash-sale countdowns and many categories.', 'تصميم سوق نابض بالعروض وعدادات التخفيضات السريعة وأقسام متعددة.', ['grocery', 'electronics', 'kids']],
        ['souqify-2', ThemeFamily::Souqify, 2, 'Souqify Fresh', 'سوقيفاي فريش', 'Bright, fresh sections designed for supermarkets and daily essentials.', 'أقسام مشرقة ومنعشة مصممة للسوبرماركت والاحتياجات اليومية.', ['grocery', 'pets']],
        ['souqify-3', ThemeFamily::Souqify, 3, 'Souqify Tech', 'سوقيفاي تك', 'Spec-friendly product cards and comparison-ready rails for gadgets and devices.', 'بطاقات منتجات تعرض المواصفات وشرائط جاهزة للمقارنة للأجهزة والإلكترونيات.', ['electronics']],
        ['souqify-4', ThemeFamily::Souqify, 4, 'Souqify Home', 'سوقيفاي هوم', 'Room-style banners and wide product grids for furniture and home appliances.', 'لافتات بأسلوب الغرف وشبكات منتجات واسعة للأثاث والأجهزة المنزلية.', ['furniture', 'electronics']],
        ['souqify-5', ThemeFamily::Souqify, 5, 'Souqify Kids', 'سوقيفاي كيدز', 'Playful colours and friendly cards for toys, baby care and school supplies.', 'ألوان مرحة وبطاقات ودودة للألعاب ومستلزمات الأطفال والمدرسة.', ['kids', 'pets']],
        ['souqify-6', ThemeFamily::Souqify, 6, 'Souqify Style', 'سوقيفاي ستايل', 'Trend-led collections and lookbook banners for fashion and sport retailers.', 'مجموعات عصرية ولافتات عرض أزياء لمتاجر الموضة والرياضة.', ['fashion', 'fitness', 'beauty']],
    ];

    public function run(): void
    {
        $categories = [];

        foreach (self::CATEGORIES as $slug => [$en, $ar, $sort]) {
            $categories[$slug] = ThemeCategory::query()->updateOrCreate(['slug' => $slug], [
                'name' => ['en' => $en, 'ar' => $ar],
                'sort_order' => $sort,
            ])->id;
        }

        foreach (self::THEMES as $index => [$slug, $family, $variant, $nameEn, $nameAr, $descriptionEn, $descriptionAr, $themeCategories]) {
            $theme = Theme::query()->updateOrCreate(['slug' => $slug], [
                'family' => $family,
                'variant' => $variant,
                'name' => ['en' => $nameEn, 'ar' => $nameAr],
                'description' => ['en' => $descriptionEn, 'ar' => $descriptionAr],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $theme->categories()->sync(array_map(fn (string $category): int => $categories[$category], $themeCategories));
        }
    }
}
