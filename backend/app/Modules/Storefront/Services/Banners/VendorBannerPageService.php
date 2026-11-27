<?php

namespace App\Modules\Storefront\Services\Banners;

use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Services\EffectivePlanLimits;
use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Storefront\DTOs\BannerInputData;
use App\Modules\Storefront\DTOs\VendorBannerRow;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Enums\ContentSource;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\HiddenBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreBannerRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Vendor › Your store › Banners (VEN-035 / 035a) page model on top of the P7 banner API
 * (BannerCatalog + StoreBannerService, decision #77): country tabs = the store's selling
 * countries, the merged list (store banners from the tenant DB, central defaults from
 * the central DB — never copied, #65 b), the plan's banner quota (PlanLimit::Banners,
 * store-owned banners only) and the "show platform banners" switch (hides / shows every
 * central banner that targets the store). Tenant context required.
 */
class VendorBannerPageService
{
    public function __construct(
        private readonly BannerCatalog $catalog,
        private readonly BannerRepositoryContract $central,
        private readonly StoreBannerRepositoryContract $store,
        private readonly HiddenBannerRepositoryContract $hidden,
        private readonly StoreBannerService $banners,
        private readonly CountryService $countries,
        private readonly EffectivePlanLimits $limits,
    ) {}

    /**
     * The store's selling countries (id => name); every active country when none is set.
     *
     * @return array<int, string>
     */
    public function countries(): array
    {
        $options = $this->countries->options();
        $selling = array_map('intval', StoreContext::tenant()->selling_country_ids ?? []);

        return $selling === [] ? $options : array_intersect_key($options, array_flip($selling));
    }

    /**
     * The requested country tab: 0 = "All" (every banner, the default), or one of the store's countries.
     *
     * @param  array<int, string>  $countries
     */
    public static function countryOrDefault(mixed $value, array $countries): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($countries === []) {
            return null;
        }

        return is_int($id) && array_key_exists($id, $countries) ? $id : 0;
    }

    /**
     * Banner count per country tab (every placement, central + store, hidden included).
     *
     * @param  list<int>  $countryIds
     * @return array<int, int>
     */
    public function counts(array $countryIds): array
    {
        $counts = [0 => count($this->catalog->all())];

        foreach ($countryIds as $countryId) {
            $counts[$countryId] = count($this->catalog->all(null, $countryId));
        }

        return $counts;
    }

    /**
     * The merged list of a placement in a country, in storefront order.
     *
     * @return Collection<int, VendorBannerRow>
     */
    public function rows(BannerPlacement $placement, ?int $countryId, ?string $search = null): Collection
    {
        $tenantId = $this->tenantId();
        $central = $this->central->applicableToTenant($tenantId, $placement, false)->keyBy('id');
        $own = $this->store->listing($placement)->keyBy('id');
        $term = $search !== null && trim($search) !== '' ? mb_strtolower(trim($search)) : null;
        $rows = collect();

        foreach ($this->catalog->all($placement, $countryId > 0 ? $countryId : null) as $item) {
            $isCentral = $item->source === ContentSource::Central->value;
            $model = $isCentral ? $central->get($item->id) : $own->get($item->id);

            if (! $model instanceof Banner && ! $model instanceof StoreBanner) {
                continue;
            }

            $row = new VendorBannerRow($isCentral ? ContentSource::Central : ContentSource::Vendor, $model, $item->hidden);

            if ($term !== null && ! str_contains(mb_strtolower($row->title().' '.$model->link_url), $term)) {
                continue;
            }

            $rows->push($row);
        }

        return $rows;
    }

    public function findOwn(int $id): StoreBanner
    {
        $banner = $this->store->find($id);

        abort_unless($banner instanceof StoreBanner, 404);

        return $banner;
    }

    /**
     * A central banner that targets this store (any state), or 404.
     */
    public function findCentral(int $id): Banner
    {
        $banner = $this->central->applicableToTenant($this->tenantId())->firstWhere('id', $id);

        abort_unless($banner instanceof Banner, 404);

        return $banner;
    }

    /**
     * Whether the store shows platform banners: false only when every central banner
     * that targets the store is hidden.
     */
    public function defaultsVisible(): bool
    {
        $ids = $this->centralIds();

        return $ids === [] || array_diff($ids, $this->hidden->hiddenIds()) !== [];
    }

    public function hasCentralBanners(): bool
    {
        return $this->centralIds() !== [];
    }

    /**
     * Shows or hides every central banner that targets the store; returns how many changed.
     */
    public function setDefaultsVisible(bool $visible): int
    {
        $hidden = array_flip($this->hidden->hiddenIds());
        $changed = 0;

        foreach ($this->centralIds() as $id) {
            if (isset($hidden[$id]) === $visible) {
                $this->banners->setCentralHidden($id, ! $visible);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Store-owned banners against the store's effective quota (plan or admin override,
     * EffectivePlanLimits; max null = unlimited / no plan).
     *
     * @return array{used: int, max: int|null, reached: bool}
     */
    public function quota(): array
    {
        $used = $this->banners->count();
        $max = $this->limits->for(PlanLimit::Banners, $this->tenantId());

        return ['used' => $used, 'max' => $max, 'reached' => $max !== null && $used >= $max];
    }

    public function create(BannerInputData $data): StoreBanner
    {
        $quota = $this->quota();

        if ($quota['reached']) {
            throw ValidationException::withMessages(['image' => __('engagement::vendor_banners.limit_reached', ['max' => $quota['max']])]);
        }

        return $this->banners->create($data);
    }

    /**
     * @return list<int>
     */
    private function centralIds(): array
    {
        return $this->central->applicableToTenant($this->tenantId())->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
    }

    private function tenantId(): int
    {
        return (int) StoreContext::tenant()->getTenantKey();
    }
}
