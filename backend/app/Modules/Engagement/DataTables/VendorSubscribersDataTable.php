<?php

namespace App\Modules\Engagement\DataTables;

use App\Modules\Engagement\Enums\SubscriberSource;
use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Newsletter subscribers (VEN-026, Figma 84:106555): e-mail, language, source,
 * status, subscribed date, actions (view customer, unsubscribe / re-subscribe, delete).
 * Filters: e-mail search, language, status, source.
 *
 * @extends BaseDataTable<NewsletterSubscriber>
 */
final class VendorSubscribersDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'locale', 'status', 'source'];

    private bool $canManage = false;

    private bool $canViewCustomers = false;

    public function __construct(private readonly NewsletterSubscriberRepositoryContract $subscribers) {}

    public function withAbilities(bool $canManage, bool $canViewCustomers): self
    {
        $this->canManage = $canManage;
        $this->canViewCustomers = $canViewCustomers;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->subscribers->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->subscribers->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $languages = Locale::enabled();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('email', static fn (Builder $query, string $order) => $query->orderBy('email', $direction($order)))
            ->orderColumn('language', static fn (Builder $query, string $order) => $query->orderBy('locale', $direction($order)))
            ->orderColumn('source', static fn (Builder $query, string $order) => $query->orderBy('customer_id', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('unsubscribed_at', $direction($order)))
            ->orderColumn('subscribed', static fn (Builder $query, string $order) => $query->orderBy('subscribed_at', $direction($order))->orderBy('id', $direction($order)))
            ->addColumn('email', static fn (NewsletterSubscriber $subscriber): string => '<span class="font-semibold text-ink" dir="ltr">'.e($subscriber->email).'</span>')
            ->addColumn('language', static fn (NewsletterSubscriber $subscriber): string => e($subscriber->locale !== null ? ($languages[$subscriber->locale]['native'] ?? strtoupper($subscriber->locale)) : '—'))
            ->addColumn('source', static fn (NewsletterSubscriber $subscriber): string => e(SubscriberSource::forCustomerId($subscriber->customer_id)->label()))
            ->addColumn('status', static fn (NewsletterSubscriber $subscriber): string => view('engagement::vendor.subscribers._status', ['subscriber' => $subscriber])->render())
            ->addColumn('subscribed', static fn (NewsletterSubscriber $subscriber): string => e($subscriber->subscribed_at->translatedFormat('d M Y')))
            ->addColumn('actions', fn (NewsletterSubscriber $subscriber): string => view('engagement::vendor.subscribers._actions-cell', [
                'subscriber' => $subscriber,
                'canManage' => $this->canManage,
                'customerUrl' => $this->canViewCustomers && $subscriber->customer_id !== null ? vendor_route('sales.customers.show', ['customer' => $subscriber->customer_id]) : null,
            ])->render())
            ->rawColumns(['email', 'status', 'actions']);
    }
}
