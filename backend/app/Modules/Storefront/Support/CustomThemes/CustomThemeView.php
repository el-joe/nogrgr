<?php

namespace App\Modules\Storefront\Support\CustomThemes;

use App\Modules\Storefront\Services\CustomThemes\CustomThemeService;
use App\Modules\Storefront\Services\CustomThemes\CustomTwigThemeRenderer;
use App\Modules\Storefront\Support\StorefrontView;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Throwable;

/**
 * A storefront page rendered by the store's custom Twig theme (SF-ALL-006), returned by
 * StorefrontView::make in place of the Blade view. On any render error it logs, reports
 * the failure (vendor notification) and renders the built-in fallback theme's Blade view
 * with the same data, so shoppers never see a broken page.
 */
final class CustomThemeView implements Responsable, View
{
    private ?string $html = null;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly int $versionId,
        private readonly string $view,
        private array $data,
    ) {}

    public function toResponse($request): Response
    {
        return new Response($this->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function render(): string
    {
        if ($this->html !== null) {
            return $this->html;
        }

        $renderer = app(CustomTwigThemeRenderer::class);
        $version = $renderer->version($this->versionId);

        if ($version !== null) {
            try {
                $html = $renderer->render($version, $this->view, $this->data);

                if ($html !== null) {
                    return $this->html = $html;
                }
            } catch (Throwable $error) {
                app(CustomThemeService::class)->reportRenderFailure($version, $error);
            }
        }

        return $this->html = $this->fallback()->render();
    }

    /**
     * The built-in theme's view of this page (the resolved family keeps the fallback theme).
     */
    public function fallback(): View
    {
        return view(StorefrontView::resolve($this->view), $this->data);
    }

    public function name(): string
    {
        return $this->view;
    }

    /**
     * @param  string|array<string, mixed>  $key
     */
    public function with($key, $value = null): static
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
