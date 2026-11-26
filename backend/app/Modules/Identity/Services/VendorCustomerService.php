<?php

namespace App\Modules\Identity\Services;

use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Modules\Engagement\Services\NewsletterService;
use App\Modules\Identity\DTOs\VendorCustomerData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorCustomerRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Customers (VEN-025 / 025a / 025b): the store's customer accounts, managed by
 * its staff. Tenant context required. Customers created here have no password: they
 * sign in with "forgot password" / social login on the storefront.
 */
class VendorCustomerService extends BaseService
{
    public function __construct(
        private readonly VendorCustomerRepositoryContract $listing,
        private readonly CustomerRepositoryContract $customers,
        private readonly CustomerAddressService $addresses,
        private readonly NewsletterService $newsletter,
        private readonly NewsletterSubscriberRepositoryContract $subscribers,
        private readonly ReturnRequestRepositoryContract $returns,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws ModelNotFoundException
     */
    public function find(int $id): Customer
    {
        /** @var Customer|null $customer */
        $customer = $this->customers->find($id);

        return $customer ?? throw (new ModelNotFoundException)->setModel(Customer::class, [$id]);
    }

    /**
     * KPI cards of the list: customers, active, buyers, average lifetime value per buyer.
     *
     * @return array{customers: int, active: int, buyers: int, average: Money}
     */
    public function stats(): array
    {
        $stats = $this->listing->stats();

        return [
            'customers' => $stats['customers'],
            'active' => $stats['active'],
            'buyers' => $stats['buyers'],
            'average' => $stats['buyers'] > 0 ? Money::ofMinor(intdiv($stats['lifetime_minor'], $stats['buyers'])) : Money::zero(),
        ];
    }

    /**
     * KPI cards of the detail page: orders, spent, returns, last order.
     *
     * @return array{orders: int, spent: Money, returns: int, last_order_at: ?Carbon}
     */
    public function summary(Customer $customer): array
    {
        $figures = $this->listing->orderFigures($customer->id);
        $tenantId = (int) tenant()?->getTenantKey();

        return [
            'orders' => $figures['orders'],
            'spent' => Money::ofMinor($figures['lifetime_minor']),
            'returns' => $this->returns->forCustomer($tenantId, $customer->id, null, 1)->total(),
            'last_order_at' => $figures['last_order_at'] !== null ? Carbon::parse($figures['last_order_at']) : null,
        ];
    }

    /**
     * Whether the customer's e-mail is an active newsletter subscriber (null = no e-mail).
     */
    public function isSubscribed(Customer $customer): ?bool
    {
        if ($customer->email === null) {
            return null;
        }

        $subscriber = $this->subscribers->findByEmail($customer->email);

        return $subscriber !== null && $subscriber->unsubscribed_at === null;
    }

    public function create(VendorCustomerData $data): Customer
    {
        $customer = $this->transaction(function () use ($data): Customer {
            /** @var Customer $customer */
            $customer = $this->customers->create([
                ...$data->attributes(),
                'status' => AccountStatus::Active,
                'country_code' => $data->phoneCountry,
            ]);

            return $customer;
        });

        $this->syncNewsletter($customer, $data->newsletter);
        $this->auditor->record(AuditAction::Created, $customer);

        return $customer;
    }

    public function update(Customer $customer, VendorCustomerData $data): Customer
    {
        $previousEmail = $customer->email;

        /** @var Customer $customer */
        $customer = $this->customers->update($customer, $data->attributes());

        if ($previousEmail !== null && $previousEmail !== $customer->email) {
            $this->newsletter->unsubscribe($previousEmail);
        }

        $this->syncNewsletter($customer, $data->newsletter);
        $this->auditor->record(AuditAction::Updated, $customer);

        return $customer;
    }

    /**
     * Active ↔ suspended. A suspended customer cannot sign in; their API tokens are revoked.
     */
    public function toggleActive(Customer $customer): Customer
    {
        $status = $customer->status === AccountStatus::Active ? AccountStatus::Suspended : AccountStatus::Active;

        /** @var Customer $customer */
        $customer = $this->transaction(function () use ($customer, $status): Customer {
            if ($status === AccountStatus::Suspended) {
                $customer->tokens()->delete();
            }

            return $this->customers->update($customer, ['status' => $status]);
        });

        $this->auditor->record(AuditAction::Updated, $customer, ['status' => $status->value]);

        return $customer;
    }

    /**
     * Same erasure as the customer's own "delete account": addresses and tokens removed,
     * personal data anonymised, row soft deleted (orders keep their snapshots), newsletter
     * subscription ended.
     */
    public function delete(Customer $customer): void
    {
        $this->auditor->record(AuditAction::Deleted, $customer);

        if ($customer->email !== null) {
            $this->newsletter->unsubscribe($customer->email);
        }

        $this->transaction(function () use ($customer): void {
            $this->addresses->deleteAllFor($customer);
            $customer->tokens()->delete();

            $customer->forceFill([
                'name' => CustomerProfileService::ANONYMISED_NAME,
                'email' => null,
                'phone' => null,
                'phone_country' => null,
                'password' => null,
                'remember_token' => null,
                'locale' => null,
                'country_code' => null,
                'notification_preferences' => null,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'status' => AccountStatus::Suspended,
            ])->save();

            $customer->delete();
        });
    }

    /**
     * CSV report of the filtered list (USD).
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $query = $this->listing->listing($filters)->orderByDesc('id');

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['id', 'name', 'email', 'phone', 'status', 'orders', 'lifetime_value_usd', 'last_order_at', 'joined_at']);

            $query->chunk(500, static function ($customers) use ($out): void {
                /** @var Customer $customer */
                foreach ($customers as $customer) {
                    fputcsv($out, [
                        $customer->id,
                        $customer->name,
                        (string) $customer->email,
                        (string) $customer->phone,
                        $customer->status->value,
                        (int) $customer->getAttribute('orders_count'),
                        Money::ofMinor((int) $customer->getAttribute('lifetime_minor'))->toDecimal(),
                        (string) $customer->getAttribute('last_order_at'),
                        (string) $customer->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'customers-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function syncNewsletter(Customer $customer, bool $subscribe): void
    {
        if ($customer->email === null) {
            return;
        }

        if ($subscribe) {
            $this->newsletter->subscribe($customer->email, $customer->locale, $customer->id);
        } elseif ($this->isSubscribed($customer) === true) {
            $this->newsletter->unsubscribe($customer->email);
        }
    }
}
