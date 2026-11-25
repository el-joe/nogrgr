<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Read models of the admin vendor profile (ADM-041…041f): store info, subscription
 * history, domains and compliance. Central connection only — nothing here touches the
 * store database, so it works for pending / failed stores too.
 */
class VendorProfileService extends BaseService
{
    /** Languages every store has from provisioning (TenantLanguageService::enableCoreLanguages). */
    public const CORE_LANGUAGES = ['ar', 'en'];

    public function __construct(
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly AddonPurchaseRepositoryContract $addons,
        private readonly LanguageRepositoryContract $languages,
        private readonly CountryService $countries,
        private readonly DomainService $domains,
        private readonly ComplianceService $compliance,
        private readonly TenantProvisioningService $provisioning,
    ) {}

    public function currentSubscription(Tenant $tenant): ?Subscription
    {
        return $this->subscriptions->currentFor((int) $tenant->id)?->loadMissing('plan');
    }

    /**
     * Every subscription of the store, newest first, with plan and latest invoice.
     *
     * @return Collection<int, Subscription>
     */
    public function subscriptionHistory(Tenant $tenant): Collection
    {
        /** @var Collection<int, Subscription> $history */
        $history = $this->subscriptions->query()
            ->forTenant((int) $tenant->id)
            ->with(['plan', 'latestInvoice'])
            ->latest('id')
            ->get()
            ->toBase();

        return $history;
    }

    /**
     * @return Collection<int, DomainRequest>
     */
    public function domainRequests(Tenant $tenant): Collection
    {
        return collect($this->domains->requestsFor($tenant)->all());
    }

    /**
     * The custom domain shown on Overview: the connected one, else the newest open request.
     */
    public function customDomain(Tenant $tenant): ?DomainRequest
    {
        $requests = $this->domainRequests($tenant)->reject(fn (DomainRequest $request): bool => $request->status === DomainRequestStatus::Removed);

        return $requests->first(fn (DomainRequest $request): bool => $request->status === DomainRequestStatus::Connected)
            ?? $requests->sortByDesc('id')->first();
    }

    /**
     * Sub-domain + every custom domain that is not removed.
     */
    public function domainCount(Tenant $tenant): int
    {
        return 1 + $this->domainRequests($tenant)->reject(fn (DomainRequest $request): bool => $request->status === DomainRequestStatus::Removed)->count();
    }

    public function complianceProfile(Tenant $tenant): ?ComplianceProfile
    {
        return $this->compliance->forTenant((int) $tenant->id)?->loadMissing('documents');
    }

    /**
     * @return array{default: string|null, selling: list<string>}
     */
    public function countryNames(Tenant $tenant): array
    {
        $options = $this->countries->options();
        $selling = [];

        foreach ((array) $tenant->selling_country_ids as $id) {
            if (isset($options[(int) $id])) {
                $selling[] = $options[(int) $id];
            }
        }

        return [
            'default' => $tenant->default_country_id !== null ? ($options[$tenant->default_country_id] ?? null) : null,
            'selling' => $selling,
        ];
    }

    /**
     * Core languages plus paid language add-ons.
     *
     * @return list<array{code: string, name: string, paid: bool}>
     */
    public function languages(Tenant $tenant): array
    {
        $paid = $this->addons->forTenant((int) $tenant->id)
            ->filter(fn (AddonPurchase $purchase): bool => $purchase->type === AddonType::Language && $purchase->status === AddonPurchaseStatus::Paid)
            ->map(fn (AddonPurchase $purchase): string => $purchase->item_code)
            ->unique()
            ->reject(fn (string $code): bool => in_array($code, self::CORE_LANGUAGES, true))
            ->values()
            ->all();

        $rows = [];

        foreach ([...self::CORE_LANGUAGES, ...$paid] as $code) {
            $rows[] = [
                'code' => $code,
                'name' => $this->languages->findByCode($code)->name ?? strtoupper($code),
                'paid' => ! in_array($code, self::CORE_LANGUAGES, true),
            ];
        }

        return $rows;
    }

    /**
     * Basis points as a localized percentage: 6250 → "62.5%".
     */
    public static function percent(int $bps): string
    {
        return Number::format($bps / 100, maxPrecision: 2, locale: Locale::numberLocale()).'%';
    }

    /**
     * Basis points as a form value: 6250 → "62.50" (dot decimal, for number inputs).
     */
    public static function percentInput(int $bps): string
    {
        return number_format($bps / 100, 2, '.', '');
    }

    public function canRetryProvisioning(Tenant $tenant): bool
    {
        return $this->provisioning->canRun($tenant);
    }
}
