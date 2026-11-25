<?php

namespace App\Modules\Notifications\DataTables;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\ReadState;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Services\VendorNotificationInboxService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Notifications (VEN-090, Figma 12:17282): the signed-in staff member's rows of
 * the store's `notifications` table, newest first, server-side. Filters: category, state.
 *
 * @extends BaseDataTable<PanelNotificationRecord>
 */
final class VendorNotificationsDataTable extends BaseDataTable
{
    public const FILTERS = ['category', 'state'];

    private ?Model $owner = null;

    public function __construct(private readonly VendorNotificationInboxService $inbox) {}

    public function for(Model $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->inbox->query($this->owner ?? throw new LogicException('The inbox table needs its owner.'));
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $category = NotificationCategory::tryFrom((string) ($filters['category'] ?? ''));
        $state = ReadState::tryFrom((string) ($filters['state'] ?? ''));

        return $query
            ->when($category !== null, fn (Builder $query) => $query->where('type', 'like', $category?->value.'.%'))
            ->when($state === ReadState::Unread, fn (Builder $query) => $query->whereNull('read_at'))
            ->when($state === ReadState::Read, fn (Builder $query) => $query->whereNotNull('read_at'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('created_at', $order === 'asc' ? 'asc' : 'desc'))
            ->addColumn('notification', static fn (PanelNotificationRecord $notification): string => view('notifications::vendor._row', ['notification' => $notification])->render())
            ->rawColumns(['notification'])
            ->only(['id', 'notification', 'created_at']);
    }
}
