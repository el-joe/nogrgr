<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\DataTables\VendorStorePagesDataTable;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Http\Requests\Vendor\StorePageRequest;
use App\Modules\Storefront\Http\Requests\Vendor\TogglePageFlagRequest;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Pages\StorePageService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Your store › Pages (VEN-033 / 033a, Figma Vendor Panel 85:106165): the store's
 * CMS pages (system pages seeded per store + its own), publish / footer switches, delete
 * (custom pages only), create / edit with a language tab per store language (title, slug,
 * rich-text body, meta description). Tenant context (store_pages).
 */
class StorePageController extends Controller
{
    public function __construct(
        private readonly StorePageService $pages,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(Request $request): View
    {
        return view('storefront::vendor.pages.index', [
            'canManage' => $this->canManage($request),
            'statuses' => ['published' => __('content::store_pages.status.published'), 'draft' => __('content::store_pages.status.draft')],
            'types' => StorePageType::options(),
        ]);
    }

    public function data(Request $request, VendorStorePagesDataTable $table): JsonResponse
    {
        return $table->withAbilities($this->canManage($request))->toJsonFromRequest($request, VendorStorePagesDataTable::FILTERS);
    }

    public function create(): View
    {
        return $this->form(new StorePage(['is_active' => true, 'show_in_footer' => true, 'type' => StorePageType::Custom]));
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $page = $this->pages->create($request->toData());

        return JsonResponder::created(__('content::store_pages.created', ['title' => (string) $page->title]), redirect: vendor_route('store.pages.edit', ['page' => $page->id]));
    }

    public function edit(int $page): View
    {
        return $this->form($this->pages->find($page));
    }

    public function update(StorePageRequest $request, int $page): JsonResponse
    {
        $row = $this->pages->update($this->pages->find($page), $request->toData());

        return JsonResponder::success(__('content::store_pages.updated', ['title' => (string) $row->title]), ['slug' => $row->slug]);
    }

    public function toggle(TogglePageFlagRequest $request, int $page): JsonResponse
    {
        $row = $this->pages->setFlag($this->pages->find($page), $request->flag(), $request->value());
        $key = $request->flag() === 'is_active'
            ? ($request->value() ? 'published' : 'unpublished')
            : ($request->value() ? 'footer_on' : 'footer_off');

        return JsonResponder::success(__('content::store_pages.'.$key, ['title' => (string) $row->title]));
    }

    public function destroy(int $page): JsonResponse
    {
        $row = $this->pages->find($page);
        $title = (string) $row->title;
        $this->pages->delete($row);

        return JsonResponder::success(__('content::store_pages.deleted', ['title' => $title]));
    }

    private function form(StorePage $page): View
    {
        $tenant = tenant();

        return view('storefront::vendor.pages.form', [
            'page' => $page,
            'locales' => $this->locales->all(),
            'storeUrl' => $tenant instanceof Tenant ? StoreSettingsService::storeUrl($tenant) : null,
        ]);
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('store.pages.manage');
    }
}
