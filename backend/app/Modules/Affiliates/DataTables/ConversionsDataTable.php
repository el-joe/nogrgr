<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Enums\ConversionSource;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * ADM-061 (Figma 74:67899): conversion + date, affiliate + source (code / link),
 * referred store + sale, commission, status, view / reverse / reject.
 *
 * @extends BaseDataTable<AffiliateConversion>
 */
final class ConversionsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'source'];

    private bool $canManage = false;

    public function __construct(private readonly ConversionRepositoryContract $conversions) {}

    public function abilities(bool $canManage): self
    {
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->conversions->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->conversions->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $locale = app()->getLocale();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('conversion', static fn (Builder $query, string $order) => $query->orderBy('created_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('commission', static fn (Builder $query, string $order) => $query->orderBy('commission_amount', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('conversion', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.person', [
                'title' => $conversion->reference(),
                'subtitle' => $conversion->created_at?->locale($locale)->translatedFormat('j M Y'),
            ])->render())
            ->addColumn('affiliate', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.person', [
                'title' => $conversion->affiliate->name ?? '—',
                'subtitle' => $conversion->source === ConversionSource::PromoCode
                    ? __('affiliates::admin.conversions.via_code', ['code' => $conversion->promoCode->code ?? '—'])
                    : __('affiliates::admin.conversions.via_link'),
            ])->render())
            ->addColumn('store', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.person', [
                'title' => $conversion->tenant->name ?? '—',
                'subtitle' => $conversion->sale_amount > 0
                    ? __('affiliates::admin.conversions.sale', ['amount' => Money::ofMinor($conversion->sale_amount)->format()])
                    : __('affiliates::admin.conversions.awaiting_payment'),
            ])->render())
            ->addColumn('commission', static fn (AffiliateConversion $conversion): string => '<span class="font-semibold text-ink" dir="ltr">'.e($conversion->commission()->format()).'</span>'
                .'<span class="block text-xs text-ink-secondary" dir="ltr">'.e($conversion->rateLabel()).'</span>')
            ->addColumn('status', static fn (AffiliateConversion $conversion): string => view('affiliates::admin.partials.badge', ['tone' => $conversion->status->tone(), 'label' => $conversion->status->label()])->render())
            ->addColumn('actions', fn (AffiliateConversion $conversion): string => view('affiliates::admin.conversions._actions', ['conversion' => $conversion, 'canManage' => $this->canManage])->render())
            ->rawColumns(['conversion', 'affiliate', 'store', 'commission', 'status', 'actions']);
    }
}
