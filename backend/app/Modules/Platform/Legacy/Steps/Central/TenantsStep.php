<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Enums\LegacyWriteResult;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\TenantProvisioningService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Old tenants (uuid ids, attributes in `data`) → owners + tenants (bigint ids) +
 * domains + central product assignments. A new store is created Pending with the
 * provisioning payload and provisioned synchronously by TenantProvisioningService
 * (DB + migrations + core languages + static texts + owner staff with the OLD
 * password hash + plan), exactly like a sign-up. Old suspended / cancelled stores get
 * that status back afterwards. Re-runs only update the store row.
 */
class TenantsStep implements LegacyStepHandler
{
    public function __construct(private readonly TenantProvisioningService $provisioning) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Tenants;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();
        $owners = $source->getSchemaBuilder()->hasTable('tenant_owners') ? $source->table('tenant_owners')->get()->keyBy('tenant_id') : collect();
        $packages = $source->table('packages')->pluck('term', 'id')->all();

        foreach ($source->table('tenants')->orderBy('created_at')->orderBy('id')->get() as $row) {
            if (! $context->options->wantsTenant((string) $row->id)) {
                continue;
            }

            $context->report->read('central', 'tenants');
            $data = LegacyValues::json($row->data) ?? [];
            $legacyId = (string) $row->id;
            $ownerRow = $owners->get($legacyId);
            $ownerId = $this->owner($context, $legacyId, $data, $ownerRow);
            $tenantId = $this->tenant($context, $legacyId, $data, $row, $ownerId, $ownerRow, $packages);

            $this->domains($context, $legacyId, $tenantId, (string) ($data['slug'] ?? ''));
            $this->assignments($context, $legacyId, $tenantId);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function owner(LegacyContext $context, string $legacyId, array $data, ?object $ownerRow): ?int
    {
        $email = Str::lower(trim((string) ($ownerRow->email ?? $data['email'] ?? '')));

        if ($email === '') {
            $context->report->issue(LegacyIssueLevel::Error, 'central', 'owners', $legacyId, 'Store without an owner e-mail');

            return null;
        }

        $context->report->read('central', 'owners');
        $hash = $ownerRow->password ?? $data['password'] ?? null;
        $provider = $ownerRow->provider ?? null;

        return $context->central->upsert('owner', $email, 'owners', array_filter([
            'name' => (string) ($data['name'] ?? $data['shop_name'] ?? $email),
            'email' => $email,
            'phone' => isset($data['phone']) ? mb_substr((string) $data['phone'], 0, 32) : null,
            'password' => is_string($hash) && $hash !== '' ? $hash : Hash::make(Str::password(32)),
            'google_id' => $provider === 'google' ? $ownerRow->provider_id : null,
            'apple_id' => $provider === 'apple' ? $ownerRow->provider_id : null,
            'status' => AccountStatus::Active->value,
            'email_verified_at' => $data['activated_at'] ?? now(),
        ], static fn (mixed $value): bool => $value !== null), ['email' => $email]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int|string, string>  $packages
     */
    private function tenant(LegacyContext $context, string $legacyId, array $data, object $row, ?int $ownerId, ?object $ownerRow, array $packages): int
    {
        $writer = $context->central;
        $existing = $writer->id('tenant', $legacyId);
        $countries = array_values(array_filter(array_map(fn (int $id): ?int => $writer->id('country', $id), LegacyValues::intList(json_encode($data['country_ids'] ?? [])))));
        $categories = array_values(array_filter(array_map(fn (int $id): ?int => $writer->id('category', $id), LegacyValues::intList(json_encode($data['category_ids'] ?? [])))));
        $slug = LegacyUnique::value($writer, 'tenants', 'slug', Str::slug((string) ($data['slug'] ?? $legacyId)) ?: 'store-'.substr(md5($legacyId), 0, 8), $existing, substr(md5($legacyId), 0, 6), 63);
        $attributes = [
            'name' => (string) ($data['shop_name'] ?? $data['name'] ?? $slug),
            'slug' => $slug,
            'owner_id' => $ownerId,
            'owner_name' => (string) ($data['name'] ?? ''),
            'owner_email' => Str::lower((string) ($ownerRow->email ?? $data['email'] ?? '')),
            'owner_phone' => isset($data['phone']) ? mb_substr((string) $data['phone'], 0, 32) : null,
            'default_country_id' => $countries[0] ?? null,
            'selling_country_ids' => $countries === [] ? null : $countries,
            'selling_category_ids' => $categories === [] ? null : $categories,
            'profit_percent_bps' => LegacyValues::bps($data['profit_percentage'] ?? 0),
        ];

        if ($existing !== null) {
            return $writer->upsert('tenant', $legacyId, 'tenants', $attributes);
        }

        if ($context->dryRun()) {
            $context->report->note("Store {$legacyId} would be created and provisioned as \"{$slug}\".");

            return $writer->upsert('tenant', $legacyId, 'tenants', $attributes);
        }

        $planId = isset($data['package_id']) ? $writer->id('plan', (int) $data['package_id']) : null;
        $trialEnds = isset($data['trial_ends_at']) ? CarbonImmutable::parse((string) $data['trial_ends_at']) : null;
        $tenant = new Tenant($attributes + ['status' => TenantStatus::Pending]);
        $tenant->setAttribute(TenantProvisioningService::PAYLOAD, [
            'owner_password_hash' => $this->usableHash($context, $legacyId, $ownerRow->password ?? null),
            'locale' => null,
            'plan_id' => $planId,
            'term' => ($packages[$data['package_id'] ?? 0] ?? null) === 'yearly' ? BillingTerm::Yearly->value : BillingTerm::Monthly->value,
            'plan_assignment' => ($trialEnds !== null && $trialEnds->isFuture() ? PlanAssignment::Trial : PlanAssignment::ManualPaid)->value,
            'legacy_id' => $legacyId,
        ]);
        $tenant->setInternal('create_database', false);
        $tenant->save();
        $tenant->forceFill(['created_at' => $row->created_at])->saveQuietly();
        $tenant->domains()->create(['domain' => $slug, 'type' => DomainType::Subdomain, 'is_primary' => true]);
        $writer->link('tenant', $legacyId, (int) $tenant->id);
        $context->report->wrote('central', 'tenants', LegacyWriteResult::Created);

        $tenant = $this->provisioning->run($tenant);

        if ($tenant->status !== TenantStatus::Active) {
            $context->report->issue(LegacyIssueLevel::Error, 'central', 'tenants', $legacyId, 'Provisioning failed: '.Str::limit((string) $tenant->provisioning_error, 200));
        } elseif (in_array($data['status'] ?? 'active', ['suspended', 'inactive', 'blocked'], true)) {
            $tenant->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => now()])->save();
        }

        return (int) $tenant->id;
    }

    /**
     * The old bcrypt hash when this app can verify it (same algorithm, cost not above ours), else null = random password + reset.
     */
    private function usableHash(LegacyContext $context, string $legacyId, mixed $hash): ?string
    {
        if (is_string($hash) && $hash !== '' && Hash::isHashed($hash) && Hash::verifyConfiguration($hash)) {
            return $hash;
        }

        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'owners', $legacyId, 'Owner password hash not reusable (owner must reset the password)');

        return null;
    }

    private function domains(LegacyContext $context, string $legacyId, int $tenantId, string $slug): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('domains')->where('tenant_id', $legacyId)->orderBy('id')->get() as $row) {
            $context->report->read('central', 'domains');
            $host = Str::lower((string) $row->domain);
            $label = Str::before($host, '.');

            if ($label === $slug || $label === Str::slug($slug)) {
                $context->report->skipped('central', 'domains'); // the store sub-domain is created with the store

                continue;
            }

            if ($writer->connection()->table('domains')->where('domain', $host)->where('tenant_id', '!=', $tenantId)->exists()) {
                $context->report->issue(LegacyIssueLevel::Error, 'central', 'domains', (int) $row->id, 'Custom domain already used by another store');

                continue;
            }

            $writer->upsert('domain', (int) $row->id, 'domains', [
                'domain' => $host,
                'tenant_id' => $tenantId,
                'type' => DomainType::Custom->value,
                'is_primary' => false,
                'created_at' => $row->created_at,
            ], ['domain' => $host]);
        }
    }

    private function assignments(LegacyContext $context, string $legacyId, int $tenantId): void
    {
        $writer = $context->central;
        $rows = [];

        foreach ($context->source->central()->table('product_tenant_assignments')->where('tenant_id', $legacyId)->get() as $row) {
            $productId = $writer->id('product', (int) $row->product_id);

            if ($productId !== null) {
                $rows[$productId] = ['product_id' => $productId, 'tenant_id' => $tenantId, 'created_at' => $row->created_at ?? now(), 'updated_at' => now()];
            }
        }

        $writer->syncPivot('product_tenant_assignments', 'tenant_id', $tenantId, array_values($rows));
    }
}
