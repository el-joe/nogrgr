<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\Enums\FooterColumn;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Http\Requests\Vendor\MenuLinkRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ReorderMenuLinksRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ToggleActiveRequest;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Services\Appearance\StoreAppearanceService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appearance › Header links (VEN-034d) and the footer's custom links (VEN-034e): add /
 * edit in a modal, on / off, drag to sort, delete.
 */
class MenuLinkController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly StoreAppearanceService $appearance,
        private readonly CatalogLocales $locales,
    ) {}

    public function create(Request $request): View
    {
        $placement = MenuLinkPlacement::tryFrom((string) $request->query('placement')) ?? MenuLinkPlacement::Header;

        return $this->form($request, new StoreMenuLink(['placement' => $placement, 'is_active' => true, 'footer_column' => $placement === MenuLinkPlacement::Footer ? FooterColumn::Company : null]));
    }

    public function store(MenuLinkRequest $request): JsonResponse
    {
        $link = $this->appearance->createLink($request->toData());

        return JsonResponder::created(__('content::store_appearance.links.created', ['label' => (string) $link->label]), redirect: $this->back($link->placement));
    }

    public function edit(Request $request, int $link): View
    {
        return $this->form($request, $this->appearance->findLink($link));
    }

    public function update(MenuLinkRequest $request, int $link): JsonResponse
    {
        $row = $this->appearance->updateLink($this->appearance->findLink($link), $request->toData());

        return JsonResponder::success(__('content::store_appearance.links.updated', ['label' => (string) $row->label]), redirect: $this->back($row->placement));
    }

    public function toggle(ToggleActiveRequest $request, int $link): JsonResponse
    {
        $row = $this->appearance->findLink($link);
        $this->appearance->setLinkActive($row, $request->value());

        return JsonResponder::success(__($request->value() ? 'content::store_appearance.links.shown' : 'content::store_appearance.links.hidden', ['label' => (string) $row->label]));
    }

    public function sort(ReorderMenuLinksRequest $request): JsonResponse
    {
        $this->appearance->reorderLinks($request->placement(), $request->ids());

        return JsonResponder::success(__('content::store_appearance.links.sorted'));
    }

    public function destroy(int $link): JsonResponse
    {
        $row = $this->appearance->findLink($link);
        $label = (string) $row->label;
        $placement = $row->placement;
        $this->appearance->deleteLink($row);

        return JsonResponder::success(__('content::store_appearance.links.deleted', ['label' => $label]), redirect: $this->back($placement));
    }

    private function form(Request $request, StoreMenuLink $link): View
    {
        $title = __($link->exists ? 'content::store_appearance.links.edit_title' : 'content::store_appearance.links.create_title');
        $back = $this->back($link->placement);

        return $this->remoteModal($request, 'storefront::vendor.appearance._link-form', [
            'link' => $link,
            'title' => $title,
            'locales' => $this->locales->all(),
            'columns' => FooterColumn::options(),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $back,
            pageModule: 'vendor-appearance',
            formId: 'appearance-link-form',
            submitLabel: __('content::store_appearance.save'),
            breadcrumbs: [__('navigation.vendor.store_design') => null, __('content::store_appearance.title') => $back, $title => null],
        ));
    }

    private function back(MenuLinkPlacement $placement): string
    {
        return vendor_route('store.appearance', ['tab' => $placement === MenuLinkPlacement::Header ? 'header' : 'footer']);
    }
}
