<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\Support\CentralSeo;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

/**
 * Templates gallery (WEB-002, Figma 12:41985) and by category (WEB-003, 12:42161).
 * Theme previews (WEB-004) arrive in P7.
 */
class TemplateController extends Controller
{
    public function __construct(
        private readonly ThemeRepositoryContract $themes,
        private readonly CentralSeo $seo,
    ) {}

    public function index(): View
    {
        return view('content::central.templates.index', [
            'seo' => $this->seo->make(
                __('content::central.seo.templates.title'),
                __('content::central.seo.templates.description'),
                [['name' => __('content::central.seo.templates.title'), 'url' => null]],
            ),
            'themes' => $this->themes->gallery(),
            'categories' => $this->themes->categories(),
            'activeCategory' => null,
        ]);
    }

    public function category(string $slug): View
    {
        $category = $this->themes->findCategoryBySlug($slug);
        abort_if($category === null, 404);

        return view('content::central.templates.index', [
            'seo' => $this->seo->make(
                __('content::central.seo.templates.category_title', ['category' => $category->name]),
                __('content::central.seo.templates.category_description', ['category' => $category->name]),
                [
                    ['name' => __('content::central.seo.templates.title'), 'url' => route('central.templates')],
                    ['name' => $category->name, 'url' => null],
                ],
            ),
            'themes' => $this->themes->gallery($category),
            'categories' => $this->themes->categories(),
            'activeCategory' => $category,
        ]);
    }
}
