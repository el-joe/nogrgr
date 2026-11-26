<?php

namespace App\Modules\Api\Services;

use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Repositories\Contracts\TenantNotificationRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer notifications inbox of the app (store DB `notifications`, the database channel
 * of every customer notification): newest first, unread filter, unread count, read one,
 * read all, delete one. Another customer's row answers null (404).
 */
class CustomerInboxService
{
    public function __construct(private readonly TenantNotificationRepositoryContract $notifications) {}

    /**
     * @return LengthAwarePaginator<int, PanelNotificationRecord>
     */
    public function page(Customer $customer, bool $unreadOnly, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->notifications->queryFor($customer)
            ->when($unreadOnly, static fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function unreadCount(Customer $customer): int
    {
        return $this->notifications->unreadCount($customer);
    }

    public function markRead(Customer $customer, string $id): ?PanelNotificationRecord
    {
        $record = $this->notifications->findFor($customer, $id);

        if ($record !== null && $record->read_at === null) {
            $record->markAsRead();
        }

        return $record;
    }

    public function markAllRead(Customer $customer): int
    {
        return $this->notifications->markAllRead($customer);
    }

    public function delete(Customer $customer, string $id): bool
    {
        $record = $this->notifications->findFor($customer, $id);

        if ($record === null) {
            return false;
        }

        $record->delete();

        return true;
    }
}
