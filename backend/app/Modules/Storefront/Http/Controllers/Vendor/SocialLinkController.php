<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Storefront\Enums\SocialNetwork;
use App\Modules\Storefront\Http\Requests\Vendor\SocialLinkRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ToggleActiveRequest;
use App\Modules\Storefront\Models\StoreSocialLink;
use App\Modules\Storefront\Services\Appearance\StoreAppearanceService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appearance › Social links (VEN-034f): one profile per network shown as footer icons on
 * every theme; add / edit in a modal, on / off, delete.
 */
class SocialLinkController extends Controller
{
    use RendersRemoteModals;

    public function __construct(private readonly StoreAppearanceService $appearance) {}

    public function create(Request $request): View
    {
        $free = array_values(array_diff(SocialNetwork::values(), $this->appearance->usedNetworks()));
        abort_if($free === [], 422, __('content::store_appearance.social.all_used'));

        return $this->form($request, new StoreSocialLink(['network' => SocialNetwork::from((string) $free[0]), 'is_active' => true]));
    }

    public function store(SocialLinkRequest $request): JsonResponse
    {
        $link = $this->appearance->createSocial($request->toData());

        return JsonResponder::created(__('content::store_appearance.social.created', ['network' => $link->network->label()]), redirect: $this->back());
    }

    public function edit(Request $request, int $link): View
    {
        return $this->form($request, $this->appearance->findSocial($link));
    }

    public function update(SocialLinkRequest $request, int $link): JsonResponse
    {
        $row = $this->appearance->updateSocial($this->appearance->findSocial($link), $request->toData());

        return JsonResponder::success(__('content::store_appearance.social.updated', ['network' => $row->network->label()]), redirect: $this->back());
    }

    public function toggle(ToggleActiveRequest $request, int $link): JsonResponse
    {
        $row = $this->appearance->findSocial($link);
        $this->appearance->setSocialActive($row, $request->value());

        return JsonResponder::success(__($request->value() ? 'content::store_appearance.social.shown' : 'content::store_appearance.social.hidden', ['network' => $row->network->label()]));
    }

    public function destroy(int $link): JsonResponse
    {
        $row = $this->appearance->findSocial($link);
        $network = $row->network->label();
        $this->appearance->deleteSocial($row);

        return JsonResponder::success(__('content::store_appearance.social.deleted', ['network' => $network]), redirect: $this->back());
    }

    private function form(Request $request, StoreSocialLink $link): View
    {
        $title = __($link->exists ? 'content::store_appearance.social.edit_title' : 'content::store_appearance.social.create_title');
        $used = $this->appearance->usedNetworks();
        $networks = array_filter(SocialNetwork::options(), static fn (string $label, string $value): bool => ! in_array($value, $used, true) || $value === $link->network?->value, ARRAY_FILTER_USE_BOTH);

        return $this->remoteModal($request, 'storefront::vendor.appearance._social-form', [
            'link' => $link,
            'title' => $title,
            'networks' => $networks,
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $this->back(),
            pageModule: 'vendor-appearance',
            formId: 'appearance-social-form',
            submitLabel: __('content::store_appearance.save'),
            breadcrumbs: [__('navigation.vendor.store_design') => null, __('content::store_appearance.title') => $this->back(), $title => null],
        ));
    }

    private function back(): string
    {
        return vendor_route('store.appearance', ['tab' => 'social']);
    }
}
