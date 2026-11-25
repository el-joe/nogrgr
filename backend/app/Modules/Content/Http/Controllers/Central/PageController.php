<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Models\Faq;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Repositories\Contracts\FaqRepositoryContract;
use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Modules\Content\Support\CentralSeo;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

/**
 * Informational central pages: How it works (WEB-006), About (WEB-007), FAQs (WEB-009),
 * Terms (WEB-010), Privacy (WEB-011) and any active static page (WEB-012, 404 when
 * inactive or storefront-only). Page bodies are admin-edited (ADM-082).
 */
class PageController extends Controller
{
    public function __construct(
        private readonly PageRepositoryContract $pages,
        private readonly FaqRepositoryContract $faqs,
        private readonly CentralSeo $seo,
    ) {}

    public function howItWorks(): View
    {
        return view('content::central.how-it-works', [
            'seo' => $this->seo->make(
                __('content::central.seo.how.title'),
                __('content::central.seo.how.description'),
                [['name' => __('content::central.seo.how.title'), 'url' => null]],
            ),
        ]);
    }

    public function about(): View
    {
        $page = $this->pages->findPublicBySlug('about');

        return view('content::central.about', [
            'seo' => $this->seo->make(
                $page?->meta_title ?: __('content::central.seo.about.title'),
                $page?->meta_description ?: __('content::central.seo.about.description'),
                [['name' => __('content::central.seo.about.title'), 'url' => null]],
            ),
            'page' => $page,
        ]);
    }

    public function faqs(): View
    {
        $faqs = $this->faqs->ordered(activeOnly: true);
        $groups = collect(FaqGroup::cases())
            ->map(fn (FaqGroup $group): array => ['group' => $group, 'items' => $faqs->filter(fn (Faq $faq): bool => $faq->group === $group)->values()])
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();

        $jsonLd = $faqs->isEmpty() ? [] : [$this->seo->faqPage($faqs->map(fn (Faq $faq): array => ['question' => $faq->question, 'answer' => $faq->answer])->all())];

        return view('content::central.faqs', [
            'seo' => $this->seo->make(
                __('content::central.seo.faqs.title'),
                __('content::central.seo.faqs.description'),
                [['name' => __('content::central.seo.faqs.title'), 'url' => null]],
                $jsonLd,
            ),
            'faqGroups' => $groups,
        ]);
    }

    public function terms(): View
    {
        return $this->render('terms', 'terms');
    }

    public function privacy(): View
    {
        return $this->render('privacy', 'privacy');
    }

    public function show(string $slug): View
    {
        abort_if(in_array($slug, Page::SYSTEM_SLUGS, true), 404);

        return $this->render($slug, 'page');
    }

    private function render(string $slug, string $kind): View
    {
        $page = $this->pages->findPublicBySlug($slug);
        abort_if($page === null, 404);

        return view('content::central.page', [
            'seo' => $this->seo->make(
                $page->meta_title ?: $page->title,
                $page->meta_description ?: (string) $page->body,
                [['name' => $page->title, 'url' => null]],
                type: 'article',
                modifiedTime: $page->updated_at?->toAtomString(),
            ),
            'page' => $page,
            'kind' => $kind,
        ]);
    }
}
