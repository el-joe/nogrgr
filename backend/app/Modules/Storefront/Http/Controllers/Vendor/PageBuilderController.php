<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\DTOs\Layouts\LayoutTargetData;
use App\Modules\Storefront\Enums\PageSectionType;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Http\Requests\Vendor\PageBuilderDraftRequest;
use App\Modules\Storefront\Http\Requests\Vendor\PageBuilderImageRequest;
use App\Modules\Storefront\Services\Layouts\PageBuilderImageStore;
use App\Modules\Storefront\Services\Layouts\PageBuilderPageService;
use App\Modules\Storefront\Services\Layouts\StoreLayoutService;
use App\Modules\Storefront\Support\ThemeSectionLibrary;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Your store › Page builder (VEN-032, decision #117 b; Figma Vendor Panel 200:33486
 * aligned to the Tenant Panel): store home per scope (store default / a selling country with
 * its own theme) and custom store pages — drag order, on / off, per-section settings, show
 * in countries, autosaved draft with a live signed preview (desktop / mobile), publish with
 * version history (restore), discard, reset to the theme default. store.design.view reads,
 * store.design.manage writes. Runs in the store's tenant context.
 */
class PageBuilderController extends Controller
{
    private function layouts(): StoreLayoutService
    {
        return app(StoreLayoutService::class);
    }

    private function pages(): PageBuilderPageService
    {
        return app(PageBuilderPageService::class);
    }

    public function index(Request $request): View
    {
        $scope = $request->query('scope');

        return $this->builder($request, $this->layouts()->homeTarget(is_string($scope) ? $scope : null));
    }

    public function page(Request $request, int $page): View
    {
        $target = $this->layouts()->pageTarget($page);
        abort_if($target === null || $target->page?->type !== StorePageType::Custom, 404);

        return $this->builder($request, $target);
    }

    public function versions(Request $request, string $target): JsonResponse
    {
        $current = $this->layouts()->target($target);

        return JsonResponder::success('', ['html' => view('storefront::vendor.page-builder._versions', [
            'target' => $current,
            'layout' => $this->layouts()->layout($current),
            'versions' => $this->layouts()->versions($current),
            'canManage' => $this->can($request, 'store.design.manage'),
        ])->render()]);
    }

    public function preview(Request $request, string $target): JsonResponse
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return JsonResponder::success('', ['url' => $this->pages()->previewUrl($tenant, $this->layouts()->target($target))]);
    }

    public function saveDraft(PageBuilderDraftRequest $request, string $target): JsonResponse
    {
        $current = $this->layouts()->target($target);
        $layout = $this->layouts()->saveDraft($current, $request->sections());

        return JsonResponder::success(__('content::page_builder.draft_saved'), [
            'state' => $layout->hasUnpublishedChanges() ? 'draft' : 'published',
            'saved_at' => $layout->draft_updated_at?->toIso8601String(),
        ]);
    }

    public function discard(string $target): JsonResponse
    {
        $current = $this->layouts()->target($target);
        $this->layouts()->discard($current);

        return JsonResponder::success(__('content::page_builder.discarded'), redirect: $this->pages()->currentUrl($current));
    }

    public function publish(Request $request, string $target): JsonResponse
    {
        $current = $this->layouts()->target($target);
        $staff = $request->user('vendor');
        $version = $this->layouts()->publish($current, $staff instanceof VendorStaff ? (int) $staff->id : null, $staff instanceof VendorStaff ? (string) $staff->name : null);

        return JsonResponder::success(__('content::page_builder.published', ['number' => $version->number]), redirect: $this->pages()->currentUrl($current));
    }

    public function reset(string $target): JsonResponse
    {
        $current = $this->layouts()->target($target);
        $this->layouts()->reset($current);

        return JsonResponder::success(__($current->isHome() ? 'content::page_builder.reset_done' : 'content::page_builder.reset_page_done'), redirect: $this->pages()->currentUrl($current));
    }

    public function restore(string $target, int $version): JsonResponse
    {
        $current = $this->layouts()->target($target);
        $restored = $this->layouts()->restore($current, $version);

        return JsonResponder::success(__('content::page_builder.restored', ['number' => $restored->number]), redirect: $this->pages()->currentUrl($current));
    }

    public function products(Request $request): JsonResponse
    {
        $term = $request->query('q');

        return response()->json($this->pages()->searchProducts(is_string($term) ? mb_substr($term, 0, 100) : null, max(1, (int) $request->query('page', '1'))));
    }

    public function image(PageBuilderImageRequest $request, PageBuilderImageStore $images): JsonResponse
    {
        $path = $images->store($request->upload());

        return JsonResponder::success(__('content::page_builder.image_uploaded'), ['path' => $path, 'url' => PageBuilderImageStore::url($path)]);
    }

    private function builder(Request $request, ?LayoutTargetData $target): View
    {
        if ($target === null) {
            return view('storefront::vendor.page-builder.index', ['target' => null, 'canManage' => false]);
        }

        $sections = $this->layouts()->editorSections($target);

        return view('storefront::vendor.page-builder.index', [
            'target' => $target,
            'targets' => $this->pages()->targets(),
            'currentUrl' => $this->pages()->currentUrl($target),
            'layout' => $this->layouts()->layout($target),
            'stale' => $this->layouts()->isStale($target),
            'sections' => $sections,
            'versions' => $this->layouts()->versions($target),
            'countries' => $this->layouts()->sellingCountries(),
            'locales' => $this->layouts()->locales(),
            'options' => $this->pages()->options($sections),
            'pageTypes' => $target->isHome() ? [] : array_map(static fn (PageSectionType $type): array => ['type' => $type, 'schema' => ThemeSectionLibrary::pageSchema($type)], PageSectionType::cases()),
            'canManage' => $this->can($request, 'store.design.manage'),
        ]);
    }

    private function can(Request $request, string $permission): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can($permission);
    }
}
