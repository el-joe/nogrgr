<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Enums\ConversionSource;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Yajra\DataTables\EloquentDataTable;

/**
 * The signed-in affiliate's own conversions (AFF-011, vendor partner page VEN-055):
 * conversion + date, referred store + source, sale, commission + rate, status.
 *
 * @extends BaseDataTable<AffiliateConversion>
 */
final class PortalConversionsDataTable extends BaseDataTable
{
    public const FILTERS = ['status'];

    private ?Affiliate $affiliate = null;

    public function __construct(private readonly ConversionRepositoryContract $conversions) {}

    public function for(Affiliate $affiliate): self
    {
        $this->affiliate = $affiliate;

        return $this;
    }

    protected function query(): Builder
    {
        if ($this->affiliate === null) {
            throw new LogicException('The conversions table needs its affiliate.');
        }

        return $this->conversions->listing(['affiliate_id' => $this->affiliate->id]);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $query->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $locale = app()->getLocale();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('conversion', static fn (Builder $query, string $order) => $query->orderBy('created_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('sale', static fn (Builder $query, string $order) => $query->orderBy('sale_amount', $direction($order)))
            ->orderColumn('commission', static fn (Builder $query, string $order) => $query->orderBy('commission_amount', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('conversion', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.person', [
                'title' => $conversion->reference(),
                'subtitle' => $conversion->created_at?->locale($locale)->translatedFormat('j M Y'),
            ])->render())
            ->addColumn('store', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.person', [
                'title' => $conversion->tenant->name ?? __('affiliates::panel.conversions.store_removed'),
                'subtitle' => $conversion->source === ConversionSource::PromoCode
                    ? __('affiliates::admin.conversions.via_code', ['code' => $conversion->promoCode->code ?? '—'])
                    : __('affiliates::admin.conversions.via_link'),
            ])->render())
            ->addColumn('sale', static fn (AffiliateConversion $conversion): string => $conversion->sale_amount > 0
                ? '<span class="text-ink" dir="ltr">'.e(Money::ofMinor($conversion->sale_amount)->format()).'</span>'
                : '<span class="text-xs text-ink-secondary">'.e(__('affiliates::admin.conversions.awaiting_payment')).'</span>')
            ->addColumn('commission', static fn (AffiliateConversion $conversion): string => '<span class="font-semibold text-ink" dir="ltr">'.e($conversion->commission()->format()).'</span>'
                .'<span class="block text-xs text-ink-secondary" dir="ltr">'.e($conversion->rateLabel()).'</span>')
            ->addColumn('status', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.badge', ['tone' => $conversion->status->tone(), 'label' => $conversion->status->label()])->render())
            ->rawColumns(['conversion', 'store', 'sale', 'commission', 'status']);
    }
}
