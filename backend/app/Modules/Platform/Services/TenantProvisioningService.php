<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\DTOs\AssignPlanData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Services\PaymentLogService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Localization\Services\StaticTexts\StaticTextSyncService;
use App\Modules\Localization\Services\TenantLanguageService;
use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Events\TenantProvisioningFailed;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Jobs\ProvisionTenant;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Support\TenantDatabaseProvisioner;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Store provisioning (central connection + the new tenant DB):
 *
 *   provision()  tenant row (pending) + primary sub-domain + optional custom-domain request,
 *                then queues ProvisionTenant on the "provisioning" queue.
 *   run()        (the job) pending|failed → provisioning → active | failed:
 *                create/migrate/seed DB → enable ar/en → copy storefront static texts → owner account (role owner) →
 *                plan via Billing → active + TenantProvisioned. Any error → failed +
 *                TenantProvisioningFailed. Every step is idempotent.
 *   retry()      failed / pending / stale provisioning → queues the job again.
 *
 * stancl's own TenantCreated pipeline is switched off per tenant (internal
 * create_database = false) so this service is the single owner of the database lifecycle.
 */
class TenantProvisioningService extends BaseService
{
    public const QUEUE = 'provisioning';

    /** Attribute (tenants.data) holding what run() needs; the password hash is wiped on success. */
    public const PAYLOAD = 'provisioning';

    public const STALE_MINUTES = 15;

    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly VendorRegistrationRepositoryContract $registrations,
        private readonly DomainRequestRepositoryContract $domainRequests,
        private readonly TenantDatabaseProvisioner $databases,
        private readonly TenantLanguageService $languages,
        private readonly VendorStaffRepositoryContract $staff,
        private readonly PlanRepositoryContract $plans,
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentLogRepositoryContract $paymentLogs,
        private readonly PaymentLogService $paymentLogService,
        private readonly StaticTextSyncService $staticTexts,
    ) {}

    public function provision(ProvisionTenantData $data): Tenant
    {
        $slug = Str::lower($data->slug);
        $host = $data->customDomain !== null && $data->customDomain !== '' ? Str::lower($data->customDomain) : null;

        if ($this->tenants->slugExists($slug) || $this->registrations->slugReserved($slug, $data->registrationId)) {
            throw PlatformRuleException::slugTaken($slug);
        }

        if ($host !== null && $this->domainRequests->hostInUse($host)) {
            throw PlatformRuleException::domainTaken($host);
        }

        $tenant = $this->transaction(function () use ($data, $slug, $host): Tenant {
            $tenant = new Tenant([
                'name' => $data->storeName,
                'slug' => $slug,
                'owner_id' => $data->ownerId,
                'status' => TenantStatus::Pending,
                'owner_name' => $data->ownerName,
                'owner_email' => Str::lower($data->ownerEmail),
                'owner_phone' => $data->ownerPhone,
                'default_country_id' => $data->defaultCountryId,
                'selling_country_ids' => $data->sellingCountryIds === [] ? null : array_values(array_map('intval', $data->sellingCountryIds)),
                'profit_percent_bps' => $data->profitPercentBps,
            ]);
            $tenant->setAttribute(self::PAYLOAD, [
                'owner_password_hash' => $data->ownerPasswordHash ?? ($data->ownerPassword !== null ? Hash::make($data->ownerPassword) : null),
                'locale' => $data->locale,
                'plan_id' => $data->planId,
                'term' => $data->term?->value,
                'plan_assignment' => $data->planAssignment->value,
                'registration_id' => $data->registrationId,
                'admin_id' => $data->adminId,
                'payment_log_id' => $data->paymentLogId,
            ]);
            $tenant->setInternal('create_database', false);
            $tenant->save();

            $tenant->domains()->create(['domain' => $slug, 'type' => DomainType::Subdomain, 'is_primary' => true]);

            if ($host !== null) {
                $this->domainRequests->create([
                    'tenant_id' => $tenant->id,
                    'host' => $host,
                    'verification_token' => Str::lower(Str::random(32)),
                ]);
            }

            return $tenant;
        }, $this->connection());

        ProvisionTenant::dispatch((int) $tenant->id)->onQueue(self::QUEUE)->afterCommit();

        return $tenant;
    }

    /**
     * The ProvisionTenant job body. Never throws: failures are recorded on the tenant.
     */
    public function run(Tenant $tenant): Tenant
    {
        if ($tenant->status === TenantStatus::Active) {
            return $tenant;
        }

        if (! $this->canRun($tenant)) {
            return $tenant;
        }

        $this->tenants->update($tenant, [
            'status' => TenantStatus::Provisioning,
            'provisioning_attempts' => $tenant->provisioning_attempts + 1,
            'provisioning_error' => null,
        ]);

        try {
            $this->databases->ensure($tenant);
            $payload = $this->payload($tenant);

            $tenant->run(function () use ($tenant, $payload): void {
                $this->languages->enableCoreLanguages();
                $this->staticTexts->syncCurrentTenant();
                $this->ensureOwner($tenant, $payload);
            });

            $this->ensurePlan($tenant, $payload);

            unset($payload['owner_password_hash']);
            $tenant->setAttribute(self::PAYLOAD, $payload);
            $this->tenants->update($tenant, [
                'status' => TenantStatus::Active,
                'provisioned_at' => now(),
                'provisioning_error' => null,
            ]);
        } catch (Throwable $exception) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            report($exception);
            $message = Str::limit($exception->getMessage(), 1000);
            $this->tenants->update($tenant, ['status' => TenantStatus::Failed, 'provisioning_error' => $message]);
            event(new TenantProvisioningFailed($tenant, $message));

            return $tenant;
        }

        event(new TenantProvisioned($tenant));

        return $tenant;
    }

    public function retry(Tenant $tenant): Tenant
    {
        if (! $this->canRun($tenant)) {
            throw PlatformRuleException::cannotRetry();
        }

        ProvisionTenant::dispatch((int) $tenant->id)->onQueue(self::QUEUE);

        return $tenant;
    }

    /**
     * Pending / failed stores, or a run that has been "provisioning" for longer than
     * STALE_MINUTES (its worker died) — never two live runs for one store.
     */
    public function canRun(Tenant $tenant): bool
    {
        if ($tenant->status->canRetryProvisioning()) {
            return true;
        }

        return $tenant->status === TenantStatus::Provisioning
            && $tenant->updated_at !== null
            && $tenant->updated_at->lt(now()->subMinutes(self::STALE_MINUTES));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Tenant $tenant): array
    {
        $payload = $tenant->getAttribute(self::PAYLOAD);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureOwner(Tenant $tenant, array $payload): void
    {
        $email = (string) $tenant->owner_email;

        /** @var VendorStaff|null $owner */
        $owner = $this->staff->query()->where('email', $email)->first();

        if ($owner === null) {
            $hash = $payload['owner_password_hash'] ?? null;

            /** @var VendorStaff $owner */
            $owner = $this->staff->create([
                'name' => (string) $tenant->owner_name,
                'email' => $email,
                'phone' => $tenant->owner_phone,
                'password' => is_string($hash) && $hash !== '' ? $hash : Hash::make(Str::password(32)),
                'status' => AccountStatus::Active,
                'locale' => is_string($payload['locale'] ?? null) ? $payload['locale'] : null,
                'is_owner' => true,
            ]);
        }

        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensurePlan(Tenant $tenant, array $payload): void
    {
        $planId = filter_var($payload['plan_id'] ?? null, FILTER_VALIDATE_INT);

        if ($planId === false || $planId === null || $this->subscriptions->currentFor((int) $tenant->id) !== null) {
            return;
        }

        /** @var Plan $plan */
        $plan = $this->plans->findOrFail($planId);
        $term = BillingTerm::tryFrom((string) ($payload['term'] ?? '')) ?? BillingTerm::Monthly;
        $assignment = PlanAssignment::tryFrom((string) ($payload['plan_assignment'] ?? '')) ?? PlanAssignment::Trial;
        $adminId = filter_var($payload['admin_id'] ?? null, FILTER_VALIDATE_INT);
        $adminId = $adminId === false ? null : $adminId;

        match ($assignment) {
            PlanAssignment::Trial => $this->subscriptionService->subscribe((int) $tenant->id, $plan, $term, null, true, $adminId),
            PlanAssignment::Invoiced => $this->subscriptionService->subscribe((int) $tenant->id, $plan, $term, null, false, $adminId),
            PlanAssignment::ManualPaid => $this->subscriptionService->assign(new AssignPlanData((int) $tenant->id, $plan->id, $term, true, $adminId)),
            PlanAssignment::Prepaid => $this->subscribePrepaid($tenant, $plan, $term, $payload),
        };
    }

    /**
     * Paid at sign-up: active subscription (no trial) whose invoice is settled by the
     * captured registration payment. Without a captured payment the invoice stays open.
     *
     * @param  array<string, mixed>  $payload
     */
    private function subscribePrepaid(Tenant $tenant, Plan $plan, BillingTerm $term, array $payload): Subscription
    {
        $logId = filter_var($payload['payment_log_id'] ?? null, FILTER_VALIDATE_INT);
        $log = $logId === false || $logId === null ? null : $this->paymentLogs->find($logId);
        $gateway = $log instanceof PaymentLog ? $log->gateway : null;

        $subscription = $this->subscriptionService->subscribe((int) $tenant->id, $plan, $term, $gateway, false);
        $invoice = $subscription->latestInvoice()->withoutGlobalScopes()->first();

        if ($log instanceof PaymentLog && $invoice instanceof Invoice) {
            $this->paymentLogService->settleWithInvoice($log, (int) $tenant->id, $invoice);
        }

        return $subscription;
    }

    private function connection(): ?string
    {
        return (new Tenant)->getConnectionName();
    }
}
