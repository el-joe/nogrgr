<?php

namespace App\Modules\Notifications\DataTables;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\ReadState;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Repositories\Contracts\PanelNotificationRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use LogicException;
use Yajra\DataTables\EloquentDataTable;

/**
 * Inbox list of the signed-in panel user (server-side). Filters: category chip,
 * read state. Newest first.
 *
 * @extends BaseDataTable<PanelNotificationRecord>
 */
final class PanelNotificationsDataTable extends BaseDataTable
{
    public const FILTERS = ['category', 'state'];

    private ?Model $owner = null;

    private string $surface = 'admin';

    private string $cellView = 'notifications::admin._notification-cell';

    public function __construct(private readonly PanelNotificationRepositoryContract $notifications) {}

    public function for(Model $owner, string $surface): self
    {
        $this->owner = $owner;
        $this->surface = $surface;

        return $this;
    }

    /**
     * Another row partial for the `notification` column (owner portal list, OWN-008).
     */
    public function withCellView(string $view): self
    {
        $this->cellView = $view;

        return $this;
    }

    protected function query(): Builder
    {
        if ($this->owner === null) {
            throw new LogicException('The inbox table needs its owner.');
        }

        return $this->notifications->queryFor($this->owner);
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
        $surface = $this->surface;
        $cellView = $this->cellView;

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('created_at', $order === 'asc' ? 'asc' : 'desc'))
            ->addColumn('notification', fn (PanelNotificationRecord $notification): string => view($cellView, ['notification' => $notification, 'surface' => $surface])->render())
            ->editColumn('created_at', fn (PanelNotificationRecord $notification): string => $notification->created_at === null ? '' : '<time datetime="'.e($notification->created_at->toIso8601String()).'" title="'.e($notification->created_at->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm')).'">'.e($notification->created_at->locale(app()->getLocale())->diffForHumans()).'</time>')
            // The row menu (mark / delete) only where the surface has it; the owner row carries its own buttons.
            ->addColumn('actions', fn (PanelNotificationRecord $notification): string => Route::has($surface.'.notifications.destroy')
                ? view('notifications::admin._actions-cell', ['notification' => $notification, 'surface' => $surface])->render()
                : '')
            ->rawColumns(['notification', 'created_at', 'actions']);
    }
}
