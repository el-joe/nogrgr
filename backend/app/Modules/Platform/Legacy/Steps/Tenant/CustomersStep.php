<?php

namespace App\Modules\Platform\Legacy\Steps\Tenant;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Store customers (bcrypt hashes kept; social provider ids kept) + addresses. Country
 * ids are re-keyed; an address without a resolvable country falls back to the
 * customer's, then is skipped and reported (the new schema requires one).
 */
class CustomersStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Customers;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->storeSource();

        if (! $context->source->hasTable($source, 'customers')) {
            return;
        }

        $iso = $context->central->connection()->table('countries')->pluck('iso2', 'id')->all();
        $hasAddresses = $context->source->hasTable($source, 'customer_addresses');

        $source->table('customers')->orderBy('id')->chunkById(500, function (Collection $rows) use ($context, $source, $iso, $hasAddresses): void {
            $addresses = $hasAddresses ? $source->table('customer_addresses')->whereIn('customer_id', $rows->pluck('id')->all())->orderBy('id')->get()->groupBy('customer_id') : collect();

            $context->storeWriter()->transaction(function () use ($rows, $context, $iso, $addresses): void {
                foreach ($rows as $row) {
                    $this->customer($context, $row, $iso, $addresses->get($row->id, collect()));
                }
            });
        });
    }

    /**
     * @param  array<int, string>  $iso
     */
    private function customer(LegacyContext $context, object $row, array $iso, Collection $addresses): void
    {
        $writer = $context->storeWriter();
        $scope = $context->scope();
        $context->report->read($scope, 'customers');
        $email = $row->email !== null ? Str::lower(trim((string) $row->email)) : null;
        $countryId = isset($row->country_id) ? $context->central->id('country', (int) $row->country_id) : null;
        $provider = $row->provider ?? null;

        $customerId = $writer->upsert('customer', (int) $row->id, 'customers', [
            'name' => (string) ($row->full_name ?? $row->name ?? $email ?? 'Customer '.$row->id),
            'email' => $email,
            'google_id' => $provider === 'google' ? $row->provider_id : null,
            'apple_id' => $provider === 'apple' ? $row->provider_id : null,
            'phone' => $row->phone !== null ? mb_substr((string) $row->phone, 0, 32) : null,
            'password' => is_string($row->password ?? null) && $row->password !== '' ? $row->password : Hash::make(Str::password(32)),
            'status' => ((bool) ($row->active ?? true) ? AccountStatus::Active : AccountStatus::Suspended)->value,
            'locale' => isset($row->language) ? mb_substr((string) $row->language, 0, 8) : null,
            'country_code' => $countryId !== null ? ($iso[$countryId] ?? null) : null,
            'email_verified_at' => $row->created_at,
            'created_at' => $row->created_at,
        ], $email !== null ? ['email' => $email] : null);

        foreach ($addresses as $address) {
            $context->report->read($scope, 'customer_addresses');
            $addressCountry = isset($address->country_id) ? $context->central->id('country', (int) $address->country_id) : null;
            $addressCountry ??= $countryId;

            if ($addressCountry === null || trim((string) $address->address_line_1) === '') {
                $context->report->issue(LegacyIssueLevel::Warning, $scope, 'customer_addresses', (int) $address->id, 'Address without country or street (skipped)');
                $context->report->skipped($scope, 'customer_addresses');

                continue;
            }

            $writer->upsert('customer_address', (int) $address->id, 'customer_addresses', [
                'customer_id' => $customerId,
                'label' => $address->label !== null ? mb_substr((string) $address->label, 0, 50) : null,
                'full_name' => mb_substr((string) ($address->full_name ?: $row->full_name ?? ''), 0, 120),
                'email' => $address->email !== null ? mb_substr((string) $address->email, 0, 120) : null,
                'phone' => mb_substr((string) ($address->phone ?? $row->phone ?? ''), 0, 20),
                'phone_country' => $iso[$addressCountry] ?? 'US',
                'address_line_1' => mb_substr((string) $address->address_line_1, 0, 300),
                'city' => mb_substr((string) ($address->city ?? ''), 0, 100),
                'state' => $address->state !== null ? mb_substr((string) $address->state, 0, 100) : null,
                'country_id' => $addressCountry,
                'is_default' => (bool) $address->is_default,
                'created_at' => $address->created_at,
            ]);
        }
    }
}
