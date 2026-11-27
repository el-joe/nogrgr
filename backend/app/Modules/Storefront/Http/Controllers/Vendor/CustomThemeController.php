<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Http\Requests\Vendor\CustomThemeSettingsRequest;
use App\Modules\Storefront\Http\Requests\Vendor\CustomThemeSubmitRequest;
use App\Modules\Storefront\Http\Requests\Vendor\CustomThemeUploadRequest;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Services\CustomThemes\CustomThemePreviewService;
use App\Modules\Storefront\Services\CustomThemes\CustomThemeService;
use App\Modules\Storefront\Services\CustomThemes\StarterThemeKit;
use App\Modules\Storefront\Support\CustomThemes\CustomThemeLimits;
use App\Modules\Storefront\Support\CustomThemes\ThemeManifest;
use App\Modules\Storefront\Support\CustomThemes\ThemeVariableReference;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeExtension;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeSecurityPolicy;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Vendor › Your store › Custom theme (VEN-036 / 036a / 036b, decision #121): upload a
 * Twig theme ZIP, validation report per version, signed preview, submit for platform
 * review, publish / roll back approved versions, switch the custom theme on / off,
 * settings form generated from theme.json, starter kit download and developer docs.
 * Runs in the store's tenant context; versions of other stores are out of scope (404).
 */
class CustomThemeController extends Controller
{
    public function __construct(
        private readonly CustomThemeService $service,
        private readonly CustomThemeRepositoryContract $themes,
        private readonly CustomThemePreviewService $previews,
    ) {}

    public function index(Request $request): View
    {
        $theme = $this->service->current();

        return view('storefront::vendor.custom-themes.index', [
            'theme' => $theme,
            'versions' => $theme !== null ? $this->themes->versions($theme) : collect(),
            'canManage' => $this->canManage($request),
            'limits' => CustomThemeLimits::documented(),
        ]);
    }

    public function show(Request $request, int $version): View
    {
        $model = $this->version($version);

        return view('storefront::vendor.custom-themes.show', [
            'version' => $model,
            'theme' => $model->theme,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function upload(CustomThemeUploadRequest $request): JsonResponse
    {
        $version = $this->service->upload($request->package(), $this->staff($request));
        $passed = $version->status !== CustomThemeVersionStatus::Failed;

        return JsonResponder::success(
            $passed ? __('content::custom_themes.upload.passed', ['version' => $version->version]) : __('content::custom_themes.upload.failed', ['count' => count($version->errors())]),
            ['status' => $version->status->value],
            redirect: vendor_route('store.custom-themes.show', ['version' => $version->id]),
        );
    }

    public function submit(CustomThemeSubmitRequest $request, int $version): JsonResponse
    {
        $model = $this->service->submit($this->version($version), $request->note());

        return JsonResponder::success(__('content::custom_themes.submitted', ['version' => $model->version]), redirect: vendor_route('store.custom-themes.show', ['version' => $model->id]));
    }

    public function withdraw(int $version): JsonResponse
    {
        $model = $this->service->withdraw($this->version($version));

        return JsonResponder::success(__('content::custom_themes.withdrawn'), redirect: vendor_route('store.custom-themes.show', ['version' => $model->id]));
    }

    public function publish(int $version): JsonResponse
    {
        $model = $this->version($version);
        $this->service->publish($model);

        return JsonResponder::success(__('content::custom_themes.published', ['version' => $model->version]), redirect: vendor_route('store.custom-themes.index'));
    }

    public function destroy(int $version): JsonResponse
    {
        $this->service->delete($this->version($version));

        return JsonResponder::success(__('content::custom_themes.deleted'), redirect: vendor_route('store.custom-themes.index'));
    }

    public function active(Request $request): JsonResponse
    {
        $active = $request->boolean('active');
        $this->service->setActive($active);

        return JsonResponder::success(__($active ? 'content::custom_themes.switched_on' : 'content::custom_themes.switched_off'), redirect: vendor_route('store.custom-themes.index'));
    }

    /**
     * Open the storefront with this version for the vendor's browser only (signed, short-lived).
     */
    public function preview(int $version): RedirectResponse
    {
        $model = $this->version($version);
        abort_unless(CustomThemePreviewService::previewable($model), 404);

        /** @var Tenant $tenant */
        $tenant = tenant();

        return redirect()->away($this->previews->url($tenant, $model));
    }

    public function settings(Request $request): View
    {
        $theme = $this->service->current();
        $version = $theme !== null ? $this->service->settingsVersion($theme) : null;

        return view('storefront::vendor.custom-themes.settings', [
            'theme' => $theme,
            'version' => $version,
            'fields' => (array) ($version?->manifest['settings'] ?? []),
            'values' => $version !== null ? ThemeManifest::settings($version->manifest, $theme?->settings) : [],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function updateSettings(CustomThemeSettingsRequest $request): JsonResponse
    {
        $theme = $this->service->current();
        abort_if($theme === null, 404);
        $this->service->saveSettings($theme, $request->values());

        return JsonResponder::success(__('content::custom_themes.settings.saved'), redirect: vendor_route('store.custom-themes.settings'));
    }

    public function starterKit(StarterThemeKit $kit): BinaryFileResponse
    {
        return response()->download($kit->zip(), StarterThemeKit::FILENAME, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /**
     * In-panel developer documentation (also linked from the Help center): the guide
     * text (content::custom_theme_docs) plus the generated variable reference, the
     * sandbox whitelist and the limits.
     */
    public function docs(): View
    {
        return view('storefront::vendor.custom-themes.docs', [
            'globals' => ThemeVariableReference::globals(),
            'pages' => array_map(static fn (CustomThemeTemplate $template): array => ['template' => $template, 'rows' => ThemeVariableReference::page($template)], CustomThemeTemplate::pages()),
            'policy' => ['tags' => ThemeSecurityPolicy::TAGS, 'filters' => ThemeSecurityPolicy::FILTERS, 'functions' => ThemeSecurityPolicy::FUNCTIONS, 'tests' => ThemeSecurityPolicy::TESTS],
            'urls' => array_keys(ThemeExtension::ROUTES),
            'limits' => CustomThemeLimits::documented(),
        ]);
    }

    private function version(int $id): CustomThemeVersion
    {
        $version = $this->themes->findVersion($id);
        abort_if($version === null || (string) $version->tenant_id !== (string) tenant()?->getTenantKey(), 404);

        return $version;
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('store.custom-themes.manage');
    }
}
