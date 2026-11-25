<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Modules\Content\Support\CentralSeo;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

/**
 * Central home (WEB-001, Figma 12:41421): hero, stats, templates gallery teaser, theme
 * categories, services, plans, reviews, latest posts and the newsletter strip.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly ThemeRepositoryContract $themes,
        private readonly PlanRepositoryContract $plans,
        private readonly BlogPostRepositoryContract $posts,
        private readonly CentralSeo $seo,
    ) {}

    public function __invoke(): View
    {
        return view('content::central.home', [
            'seo' => $this->seo->make(__('content::central.seo.home.title'), __('content::central.seo.home.description')),
            'themes' => $this->themes->gallery(limit: 6),
            'themeCategories' => $this->themes->categories(),
            'plans' => $this->plans->ordered()->filter(fn (Plan $plan): bool => $plan->status === PlanStatus::Active)->values(),
            'posts' => $this->posts->latestVisible(3),
            'stats' => null,
            'testimonials' => __('content::site.testimonials'),
        ]);
    }
}
