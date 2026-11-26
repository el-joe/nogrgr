<?php

namespace App\Modules\Storefront\Services\Appearance;

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\DTOs\Appearance\AppearanceColorsData;
use App\Modules\Storefront\DTOs\Appearance\FooterSettingsData;
use App\Modules\Storefront\DTOs\Appearance\MenuLinkData;
use App\Modules\Storefront\DTOs\Appearance\PromoBarData;
use App\Modules\Storefront\DTOs\Appearance\SocialLinkData;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Models\StoreSocialLink;
use App\Modules\Storefront\Repositories\Contracts\StoreMenuLinkRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreSocialLinkRepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Vendor › Your store › Appearance (VEN-034, tenant context): logo, colours & fonts,
 * promo bar, header links, footer and social links. Scalar settings live in the tenant
 * `store_settings` (StoreSettingKey), links in `store_menu_links` / `store_social_links`.
 * The storefront reads them through StorefrontAppearance.
 */
class StoreAppearanceService
{
    public const MAX_HEADER_LINKS = 8;

    public const MAX_FOOTER_LINKS = 24;

    public function __construct(
        private readonly StoreSettingRepositoryContract $settings,
        private readonly StoreSettingsService $storeSettings,
        private readonly StoreMenuLinkRepositoryContract $links,
        private readonly StoreSocialLinkRepositoryContract $social,
    ) {}

    public function colors(): AppearanceColorsData
    {
        return AppearanceColorsData::fromSetting($this->settings->get(StoreSettingKey::AppearanceColors));
    }

    public function saveColors(AppearanceColorsData $data): void
    {
        $data->isDefault()
            ? $this->settings->forget(StoreSettingKey::AppearanceColors)
            : $this->settings->put(StoreSettingKey::AppearanceColors, $data->toSetting());
    }

    /**
     * "Reset": back to the active theme's own colours and fonts.
     */
    public function resetColors(): void
    {
        $this->settings->forget(StoreSettingKey::AppearanceColors);
    }

    public function promo(): PromoBarData
    {
        return PromoBarData::fromSetting($this->settings->get(StoreSettingKey::PromoBar));
    }

    public function savePromo(PromoBarData $data): void
    {
        $this->settings->put(StoreSettingKey::PromoBar, $data->toSetting());
    }

    public function footer(): FooterSettingsData
    {
        return FooterSettingsData::fromSetting($this->settings->get(StoreSettingKey::FooterSettings));
    }

    public function saveFooter(FooterSettingsData $data): void
    {
        $this->settings->put(StoreSettingKey::FooterSettings, $data->toSetting());
    }

    public function logoUrl(): ?string
    {
        return $this->storeSettings->logoUrl();
    }

    public function replaceLogo(UploadedFile $file): void
    {
        $this->storeSettings->replaceLogo($file);
    }

    /**
     * @return Collection<int, StoreMenuLink>
     */
    public function links(MenuLinkPlacement $placement): Collection
    {
        return $this->links->forPlacement($placement);
    }

    public function findLink(int $id): StoreMenuLink
    {
        /** @var StoreMenuLink $link */
        $link = $this->links->findOrFail($id);

        return $link;
    }

    public function createLink(MenuLinkData $data): StoreMenuLink
    {
        $limit = $data->placement === MenuLinkPlacement::Header ? self::MAX_HEADER_LINKS : self::MAX_FOOTER_LINKS;

        if ($this->links->forPlacement($data->placement)->count() >= $limit) {
            throw ValidationException::withMessages(['label' => __('content::store_appearance.links.limit', ['max' => $limit])]);
        }

        /** @var StoreMenuLink $link */
        $link = $this->links->create($this->linkAttributes($data) + [
            'placement' => $data->placement,
            'sort_order' => $this->links->nextSortOrder($data->placement),
        ]);

        return $link;
    }

    public function updateLink(StoreMenuLink $link, MenuLinkData $data): StoreMenuLink
    {
        $link->replaceTranslations('label', $data->label);

        /** @var StoreMenuLink $updated */
        $updated = $this->links->update($link, array_diff_key($this->linkAttributes($data), ['label' => true]));

        return $updated;
    }

    public function setLinkActive(StoreMenuLink $link, bool $active): void
    {
        $this->links->update($link, ['is_active' => $active]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorderLinks(MenuLinkPlacement $placement, array $ids): void
    {
        $this->links->reorder($placement, $ids);
    }

    public function deleteLink(StoreMenuLink $link): void
    {
        $this->links->delete($link);
    }

    /**
     * @return Collection<int, StoreSocialLink>
     */
    public function socialLinks(): Collection
    {
        return $this->social->ordered();
    }

    /**
     * @return list<string>
     */
    public function usedNetworks(): array
    {
        return $this->social->usedNetworks();
    }

    public function findSocial(int $id): StoreSocialLink
    {
        /** @var StoreSocialLink $link */
        $link = $this->social->findOrFail($id);

        return $link;
    }

    public function createSocial(SocialLinkData $data): StoreSocialLink
    {
        /** @var StoreSocialLink $link */
        $link = $this->social->create([
            'network' => $data->network,
            'url' => $data->url,
            'is_active' => $data->isActive,
            'sort_order' => $this->social->nextSortOrder(),
        ]);

        return $link;
    }

    public function updateSocial(StoreSocialLink $link, SocialLinkData $data): StoreSocialLink
    {
        /** @var StoreSocialLink $updated */
        $updated = $this->social->update($link, ['network' => $data->network, 'url' => $data->url, 'is_active' => $data->isActive]);

        return $updated;
    }

    public function setSocialActive(StoreSocialLink $link, bool $active): void
    {
        $this->social->update($link, ['is_active' => $active]);
    }

    public function deleteSocial(StoreSocialLink $link): void
    {
        $this->social->delete($link);
    }

    /**
     * @return array<string, mixed>
     */
    private function linkAttributes(MenuLinkData $data): array
    {
        return [
            'label' => $data->label,
            'url' => $data->url,
            'footer_column' => $data->placement === MenuLinkPlacement::Footer ? $data->footerColumn : null,
            'is_active' => $data->isActive,
        ];
    }
}
