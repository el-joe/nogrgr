<?php

namespace App\Modules\Platform\Exports;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\VendorProfileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendors CSV (ADM-040 export) with the list's filters. UTF-8 with BOM so Excel opens
 * Arabic text correctly; percentages as decimals.
 */
final class VendorsCsvExport
{
    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly PlanRepositoryContract $plans,
        private readonly CountryService $countries,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(array $filters): StreamedResponse
    {
        $query = $this->tenants->listing($filters)->orderBy('tenants.id');
        $plans = $this->plans->options();
        $countries = $this->countries->options();
        $filename = 'vendors-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $plans, $countries): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('platform::vendors.columns.id'),
                __('platform::vendors.columns.store'),
                __('platform::vendors.columns.host'),
                __('platform::vendors.fields.owner_name'),
                __('platform::vendors.fields.owner_email'),
                __('platform::vendors.fields.owner_phone'),
                __('platform::vendors.fields.default_country'),
                __('platform::vendors.columns.plan'),
                __('platform::vendors.columns.subscription'),
                __('platform::vendors.columns.profit'),
                __('platform::vendors.fields.affiliate_commission'),
                __('platform::vendors.columns.status'),
                __('platform::vendors.columns.created'),
            ], escape: '');

            $query->chunk(500, function ($rows) use ($out, $plans, $countries): void {
                /** @var Tenant $tenant */
                foreach ($rows as $tenant) {
                    $planId = $tenant->getAttribute('plan_id');
                    $subscription = SubscriptionStatus::tryFrom((string) $tenant->getAttribute('subscription_status'));

                    fputcsv($out, [
                        $tenant->id,
                        $tenant->name,
                        $tenant->subdomainHost(),
                        (string) $tenant->owner_name,
                        (string) $tenant->owner_email,
                        (string) $tenant->owner_phone,
                        $tenant->default_country_id !== null ? ($countries[$tenant->default_country_id] ?? '') : '',
                        $planId !== null ? ($plans[(int) $planId] ?? '') : '',
                        $subscription?->label() ?? '',
                        VendorProfileService::percentInput($tenant->profit_percent_bps),
                        VendorProfileService::percentInput($tenant->affiliate_commission_bps),
                        $tenant->status->label(),
                        $tenant->created_at?->toDateTimeString() ?? '',
                    ], escape: '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
