<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\Services\ThemeShowcaseService;
use App\Modules\Content\Support\CentralSeo;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Central theme gallery `/themes` and live preview `/themes/{slug}` (WEB-004, Figma Central
 * Website additions 127:11426 · mobile view 127:11559, decisions #117 a / #119). The preview
 * shows the real theme from the platform demo store in a device frame; it is noindex.
 */
class ThemeShowcaseController extends Controller
{
    public function __construct(
        private readonly ThemeShowcaseService $showcase,
        private readonly CentralSeo $seo,
    ) {}

    public function index(): Response
    {
        $themes = $this->showcase->gallery();

        return response()->view('content::central.themes.index', [
            'seo' => $this->seo->make(
                __('content::theme_preview.seo.title'),
                __('content::theme_preview.seo.description'),
                [['name' => __('content::theme_preview.seo.title'), 'url' => null]],
            ),
            'themes' => $themes,
            'registerUrls' => $themes->mapWithKeys(fn ($theme): array => [$theme->slug => $this->showcase->registerUrl($theme)])->all(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $preview = $this->showcase->preview($slug, app()->getLocale(), $request);
        abort_if($preview === null, 404);

        return response()
            ->view('content::central.themes.preview', [
                'seo' => $this->seo->make(
                    __('content::theme_preview.seo.preview_title', ['theme' => $preview['name']]),
                    $preview['description'] ?? __('content::theme_preview.seo.description'),
                    noindex: true,
                ),
                'preview' => $preview,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
