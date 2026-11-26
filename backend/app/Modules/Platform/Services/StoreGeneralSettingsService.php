<?php

namespace App\Modules\Platform\Services;

use App\Modules\Catalog\DTOs\Requests\TenantChangeSubmissionData;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Services\Requests\TenantChangeService;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Platform\DTOs\StoreChangeRequestData;
use App\Modules\Platform\DTOs\StoreGeneralSettingsData;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use DateTimeZone;
use LogicException;

/**
 * Vendor › Settings › General (VEN-071, Figma Vendor Panel 90:112042) of the current store:
 *  - store name + default profit % live on the central tenant row (the profit is the
 *    fallback markup of StoreProductPricingService: products without their own profit row)
 *  - support e-mail + timezone live in the tenant `store_settings`
 *  - selling countries / categories are read-only here: changing them is a store change
 *    request (VEN-071a / 071b → TenantChangeService, reviewed by the admin in ADM-018i);
 *    one pending request per type, shown on the card until it is reviewed.
 */
class StoreGeneralSettingsService extends BaseService
{
    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly StoreSettingRepositoryContract $settings,
        private readonly TenantChangeService $changes,
        private readonly CountryRepositoryContract $countries,
        private readonly CategoryRepositoryContract $categories,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return array{store_name: string, support_email: string|null, profit_percent: string, timezone: string}
     */
    public function current(): array
    {
        $tenant = $this->tenant();

        return [
            'store_name' => $tenant->name,
            'support_email' => $this->supportEmail(),
            'profit_percent' => VendorProfileService::percentInput($tenant->profit_percent_bps),
            'timezone' => $this->timezone(),
        ];
    }

    public function supportEmail(): ?string
    {
        $value = $this->settings->get(StoreSettingKey::SupportEmail);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The store's timezone (panel dates, audit log), else the application timezone.
     */
    public function timezone(): string
    {
        $value = $this->settings->get(StoreSettingKey::Timezone);

        return is_string($value) && in_array($value, DateTimeZone::listIdentifiers(), true) ? $value : (string) config('app.timezone');
    }

    public function save(StoreGeneralSettingsData $data): void
    {
        $tenant = $this->tenant();
        $before = $this->current();

        $this->tenants->update($tenant, [
            'name' => $data->storeName,
            'profit_percent_bps' => $data->profitPercentBps,
        ]);

        $data->supportEmail !== null
            ? $this->settings->put(StoreSettingKey::SupportEmail, $data->supportEmail)
            : $this->settings->forget(StoreSettingKey::SupportEmail);
        $this->settings->put(StoreSettingKey::Timezone, $data->timezone);

        $after = $this->current();
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        if ($changed !== []) {
            $this->auditor->record('store.settings_updated', $tenant, [
                'before' => array_intersect_key($before, array_flip($changed)),
                'after' => array_intersect_key($after, array_flip($changed)),
            ]);
        }
    }

    /**
     * Names of the store's current selling countries / categories (id => name).
     *
     * @return array<int, string>
     */
    public function currentNames(TenantChangeType $type): array
    {
        return $this->changes->names($type, $this->currentIds($type));
    }

    /**
     * @return list<int>
     */
    public function currentIds(TenantChangeType $type): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) $this->tenant()->getAttribute($type->tenantAttribute()))));
        sort($ids);

        return $ids;
    }

    public function pending(TenantChangeType $type): ?TenantChangeRequest
    {
        return $this->changes->pendingFor($this->tenant()->id, $type);
    }

    /**
     * Names added / removed by a pending request.
     *
     * @return array{add: list<string>, remove: list<string>}
     */
    public function pendingSummary(TenantChangeRequest $request): array
    {
        $names = $this->changes->names($request->type, array_values(array_unique([...$request->addedIds(), ...$request->removedIds()])));

        return [
            'add' => array_values(array_filter(array_map(static fn (int $id): ?string => $names[$id] ?? null, $request->addedIds()))),
            'remove' => array_values(array_filter(array_map(static fn (int $id): ?string => $names[$id] ?? null, $request->removedIds()))),
        ];
    }

    /**
     * Choices of the "Add" select: active countries / top-level categories not yet on the store.
     *
     * @return array<int, string>
     */
    public function addOptions(TenantChangeType $type): array
    {
        $current = $this->currentIds($type);
        $options = [];

        $models = $type === TenantChangeType::Countries ? $this->countries->active() : $this->categories->activeTopLevel();

        foreach ($models as $model) {
            /** @var Country|Category $model */
            if (! in_array((int) $model->getKey(), $current, true)) {
                $options[(int) $model->getKey()] = (string) $model->getAttribute('name');
            }
        }

        return $options;
    }

    /**
     * Ids the "Add" select accepts.
     *
     * @return list<int>
     */
    public function addableIds(TenantChangeType $type): array
    {
        return array_keys($this->addOptions($type));
    }

    /**
     * @throws RequestRuleException pending request of that type, or nothing would change
     */
    public function requestChange(StoreChangeRequestData $data): TenantChangeRequest
    {
        $current = $this->currentIds($data->type);
        $requested = array_values(array_diff(array_unique([...$current, ...$data->add]), $data->remove));

        if ($data->type === TenantChangeType::Countries && $requested === []) {
            throw new RequestRuleException(__('platform::vendor_general.errors.last_country'));
        }

        $request = $this->changes->submit(new TenantChangeSubmissionData(
            tenantId: $this->tenant()->id,
            type: $data->type,
            requestedIds: $requested,
            vendorNote: $data->reason,
            submittedBy: $data->submittedBy,
        ));
        $this->auditor->record('store.change_requested', $request, ['type' => $data->type->value, 'add' => $data->add, 'remove' => $data->remove]);

        return $request;
    }

    private function tenant(): Tenant
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if (! $tenant instanceof Tenant) {
            throw new LogicException('Store settings are read inside tenant context.');
        }

        return $tenant;
    }
}
