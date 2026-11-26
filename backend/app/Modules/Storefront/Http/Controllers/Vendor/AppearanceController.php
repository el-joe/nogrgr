<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Enums\FooterColumn;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Enums\StoreFont;
use App\Modules\Storefront\Http\Requests\Vendor\AppearanceColorsRequest;
use App\Modules\Storefront\Http\Requests\Vendor\FooterSettingsRequest;
use App\Modules\Storefront\Http\Requests\Vendor\PromoBarRequest;
use App\Modules\Storefront\Http\Requests\Vendor\StoreLogoRequest;
use App\Modules\Storefront\Services\Appearance\StoreAppearanceService;
use App\Modules\Storefront\Support\BrandPalette;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Your store › Appearance (VEN-034, Figma Vendor Panel 85:106903 / 85:107559):
 * tabs Logo, Colours & fonts, Promo bar, Header links, Footer, Social links — each tab
 * saves on its own. Values take effect on every storefront theme (StorefrontAppearance).
 * The AI logo generator of the Logo tab is VEN-034a (P10).
 */
class AppearanceController extends Controller
{
    public const TABS = ['logo', 'colors', 'promo', 'header', 'footer', 'social'];

    public function __construct(
        private readonly StoreAppearanceService $appearance,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(Request $request): View
    {
        return view('storefront::vendor.appearance.index', [
            'tabs' => self::TABS,
            'canManage' => $this->canManage($request),
            'locales' => $this->locales->all(),
            'logoUrl' => $this->appearance->logoUrl(),
            'colors' => $this->appearance->colors(),
            'palette' => BrandPalette::COLORS,
            'ltrFonts' => StoreFont::ltrOptions(),
            'rtlFonts' => StoreFont::rtlOptions(),
            'promo' => $this->appearance->promo(),
            'headerLinks' => $this->appearance->links(MenuLinkPlacement::Header),
            'footerLinks' => $this->appearance->links(MenuLinkPlacement::Footer),
            'footerColumns' => FooterColumn::options(),
            'footer' => $this->appearance->footer(),
            'socialLinks' => $this->appearance->socialLinks(),
            'maxHeaderLinks' => StoreAppearanceService::MAX_HEADER_LINKS,
        ]);
    }

    public function logo(StoreLogoRequest $request): JsonResponse
    {
        $this->appearance->replaceLogo($request->logoFile());

        return JsonResponder::success(__('content::store_appearance.logo.saved'), ['url' => $this->appearance->logoUrl()]);
    }

    public function colors(AppearanceColorsRequest $request): JsonResponse
    {
        $this->appearance->saveColors($request->toData());

        return JsonResponder::success(__('content::store_appearance.colors.saved'));
    }

    public function resetColors(): JsonResponse
    {
        $this->appearance->resetColors();

        return JsonResponder::success(__('content::store_appearance.colors.reset_done'), redirect: vendor_route('store.appearance', ['tab' => 'colors']));
    }

    public function promo(PromoBarRequest $request): JsonResponse
    {
        $this->appearance->savePromo($request->toData());

        return JsonResponder::success(__('content::store_appearance.promo.saved'));
    }

    public function footer(FooterSettingsRequest $request): JsonResponse
    {
        $this->appearance->saveFooter($request->toData());

        return JsonResponder::success(__('content::store_appearance.footer.saved'));
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('store.appearance.manage');
    }
}
