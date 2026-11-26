<?php

use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/*
| Shared helpers of the Affiliates feature tests (required by each test file).
*/

if (! function_exists('affiliateAdmin')) {
    /**
     * @param  list<string>|null  $permissions  null = super-admin
     */
    function affiliateAdmin(?array $permissions = null): Admin
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        test()->seed(CentralPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Admin::factory()->create();

        $permissions === null
            ? $admin->assignRole('super-admin')
            : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    function affiliateTenant(string $name = 'Desert Rose', array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'name' => $name,
            'slug' => 'aff-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
            'owner_email' => Str::lower(Str::random(8)).'@stores.test',
        ], $attributes)));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    function capturedLog(Tenant $tenant, int $amount = 4900, array $attributes = []): PaymentLog
    {
        return PaymentLog::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'purpose' => PaymentPurpose::Registration,
            'status' => PaymentLogStatus::Captured,
            'amount_minor' => $amount,
            'paid_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    function conversionFor(Affiliate $affiliate, Tenant $tenant, array $attributes = []): AffiliateConversion
    {
        return AffiliateConversion::query()->create(array_merge([
            'affiliate_id' => $affiliate->id,
            'tenant_id' => $tenant->id,
            'source' => 'link',
            'commission_type' => RateType::Percentage,
            'commission_value' => 3000,
            'status' => ConversionStatus::Pending,
        ], $attributes));
    }

    /**
     * An approved conversion that credited the affiliate (balance + earned).
     */
    function approvedConversion(Affiliate $affiliate, int $commission, ?string $approvedAt = null): AffiliateConversion
    {
        $conversion = conversionFor($affiliate, affiliateTenant('Store '.Str::random(5)), [
            'status' => ConversionStatus::Approved,
            'sale_amount' => $commission * 2,
            'commission_amount' => $commission,
            'approved_at' => $approvedAt ?? now(),
        ]);
        $affiliate->forceFill(['balance' => $affiliate->balance + $commission, 'total_earned' => $affiliate->total_earned + $commission])->save();

        return $conversion;
    }
}
