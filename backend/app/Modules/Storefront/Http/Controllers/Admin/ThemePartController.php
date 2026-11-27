<?php

namespace App\Modules\Storefront\Http\Controllers\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DataTables\ThemePartsDataTable;
use App\Modules\Storefront\Enums\ThemePartType;
use App\Modules\Storefront\Http\Requests\Admin\ThemePartRequest;
use App\Modules\Storefront\Http\Requests\Admin\ToggleThemePartRequest;
use App\Modules\Storefront\Models\ThemePart;
use App\Modules\Storefront\Services\Themes\ThemeCatalogService;
use App\Modules\Storefront\Services\Themes\ThemePartService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Content › Storefront › Template parts (ADM-088 / 088a, decision #120; old
 * TemplatePartsPage / AddEditTemplatePart): server-side list filtered by theme + part
 * type, set default (confirm), on / off, delete (confirm), add / edit modal with the
 * settings the part type declares and a preview image.
 */
class ThemePartController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly ThemePartService $parts,
        private readonly ThemeCatalogService $catalog,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(Request $request): View
    {
        $themes = $this->themeOptions();
        $theme = (int) $request->query('theme', '0');
        $type = ThemePartType::tryFrom((string) $request->query('type'));

        return view('storefront::admin.template-parts.index', [
            'themes' => $themes,
            'theme' => array_key_exists($theme, $themes) ? $theme : 0,
            'type' => $type,
            'types' => ThemePartType::options(),
        ]);
    }

    public function data(Request $request, ThemePartsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ThemePartsDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        $themes = $this->themeOptions();
        $theme = (int) $request->query('theme', '0');
        $part = new ThemePart([
            'theme_id' => array_key_exists($theme, $themes) ? $theme : array_key_first($themes),
            'type' => ThemePartType::tryFrom((string) $request->query('type')) ?? ThemePartType::Announcement,
            'is_active' => true,
            'settings' => [],
        ]);

        return $this->form($request, $part);
    }

    public function store(ThemePartRequest $request): JsonResponse
    {
        $part = $this->parts->create($request->toData());

        return JsonResponder::created(__('content::admin_themes.parts.created', ['name' => $part->name]));
    }

    public function edit(Request $request, ThemePart $part): View
    {
        return $this->form($request, $part);
    }

    public function update(ThemePartRequest $request, ThemePart $part): JsonResponse
    {
        $this->parts->update($part, $request->toData());

        return JsonResponder::success(__('content::admin_themes.parts.updated', ['name' => $part->name]));
    }

    public function toggle(ToggleThemePartRequest $request, ThemePart $part): JsonResponse
    {
        $this->parts->setActive($part, $request->value());

        return JsonResponder::success(__($request->value() ? 'content::admin_themes.parts.activated' : 'content::admin_themes.parts.deactivated', ['name' => $part->name]));
    }

    public function makeDefault(ThemePart $part): JsonResponse
    {
        $this->parts->setDefault($part);

        return JsonResponder::success(__('content::admin_themes.parts.default_set', ['name' => $part->name]));
    }

    public function destroy(ThemePart $part): JsonResponse
    {
        $name = $part->name;
        $this->parts->delete($part);

        return JsonResponder::success(__('content::admin_themes.parts.deleted', ['name' => $name]));
    }

    private function form(Request $request, ThemePart $part): View
    {
        $title = $part->exists ? __('content::admin_themes.parts.edit_title') : __('content::admin_themes.parts.create_title');
        $index = route('admin.storefront.template-parts.index');

        return $this->remoteModal($request, 'storefront::admin.template-parts._form', [
            'part' => $part,
            'title' => $title,
            'themes' => $this->themeOptions(),
            'types' => ThemePartType::cases(),
            'locales' => $this->locales->all(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            formId: 'theme-part-form',
            submitLabel: __('content::admin_themes.save'),
            breadcrumbs: [__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.template_parts') => $index, $title => null],
        ));
    }

    /**
     * @return array<int, string>
     */
    private function themeOptions(): array
    {
        return $this->catalog->catalogue()->mapWithKeys(static fn ($theme): array => [(int) $theme->id => (string) $theme->name])->all();
    }
}
