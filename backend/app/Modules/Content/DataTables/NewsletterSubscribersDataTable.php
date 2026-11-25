<?php

namespace App\Modules\Content\DataTables;

use App\Modules\Content\Enums\SubscriberStatus;
use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Content\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin newsletter subscribers list (ADM-084): filter status, search e-mail.
 *
 * @extends BaseDataTable<NewsletterSubscriber>
 */
final class NewsletterSubscribersDataTable extends BaseDataTable
{
    public function __construct(private readonly NewsletterSubscriberRepositoryContract $subscribers) {}

    protected function query(): Builder
    {
        return $this->subscribers->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;

        return $query
            ->when(is_string($status) && SubscriberStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('status', $status))
            ->when(is_string($search) && $search !== '', fn (Builder $q) => $q->where('email', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('email', fn (NewsletterSubscriber $subscriber): string => '<bdi dir="ltr" class="font-medium text-ink">'.e($subscriber->email).'</bdi>')
            ->editColumn('status', fn (NewsletterSubscriber $subscriber): string => view('content::admin._badge', ['tone' => $subscriber->status->tone(), 'label' => $subscriber->status->label()])->render())
            ->editColumn('locale', fn (NewsletterSubscriber $subscriber): string => e(strtoupper($subscriber->locale)))
            ->editColumn('created_at', fn (NewsletterSubscriber $subscriber): string => '<bdi dir="ltr">'.e((string) $subscriber->created_at?->format('Y-m-d H:i')).'</bdi>')
            ->editColumn('confirmed_at', fn (NewsletterSubscriber $subscriber): string => $subscriber->confirmed_at !== null ? '<bdi dir="ltr">'.e($subscriber->confirmed_at->format('Y-m-d H:i')).'</bdi>' : '—')
            ->addColumn('actions', fn (NewsletterSubscriber $subscriber): string => view('content::admin.newsletter._actions', ['subscriber' => $subscriber])->render())
            ->rawColumns(['email', 'status', 'created_at', 'confirmed_at', 'actions']);
    }
}
