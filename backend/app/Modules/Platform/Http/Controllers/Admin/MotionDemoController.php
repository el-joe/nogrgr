<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Http\Requests\Admin\MotionDemoRequest;
use App\Modules\Platform\Support\MotionDemoSamples;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

/**
 * Storefront motion gallery (/admin/ui-kit/storefront-motion): every motion module of
 * resources/js/storefront/core/motion rendered through the chosen family's storefront
 * layout and bundle, so QA can check the per-family tokens, RTL, touch, mouse drag,
 * keyboard and reduced motion (permission system.ui-kit.view).
 */
class MotionDemoController extends Controller
{
    public function __invoke(MotionDemoRequest $request): View
    {
        if ($request->direction() !== null) {
            app()->setLocale($request->direction() === 'rtl' ? 'ar' : 'en');
        }

        $products = MotionDemoSamples::products();
        $slugs = array_column($products, 'slug');
        $requested = $request->validated('product');
        $current = is_string($requested) && in_array($requested, $slugs, true) ? $requested : $slugs[0];

        return view('platform::admin.storefront-motion', [
            'family' => $request->family(),
            'families' => MotionDemoSamples::FAMILIES,
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'products' => $products,
            'product' => collect($products)->firstWhere('slug', $current),
            'heroes' => MotionDemoSamples::heroes(),
            'gallery' => MotionDemoSamples::gallery($current),
            'flashEndsAt' => now()->addDays(2)->addHours(5)->toIso8601String(),
        ]);
    }
}
