<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Yajra\DataTables\EloquentDataTable;

/**
 * The affiliate's promo codes (AFF-010; created by the admin on ADM-063): code + copy,
 * discount, uses / limit, validity, state.
 *
 * @extends BaseDataTable<AffiliatePromoCode>
 */
final class PortalPromoCodesDataTable extends BaseDataTable
{
    private ?Affiliate $affiliate = null;

    public function __construct(private readonly PromoCodeRepositoryContract $codes) {}

    public function for(Affiliate $affiliate): self
    {
        $this->affiliate = $affiliate;

        return $this;
    }

    protected function query(): Builder
    {
        if ($this->affiliate === null) {
            throw new LogicException('The promo codes table needs its affiliate.');
        }

        return $this->codes->listing(['affiliate_id' => $this->affiliate->id]);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $locale = app()->getLocale();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('code', static fn (Builder $query, string $order) => $query->orderBy('code', $direction($order)))
            ->orderColumn('uses', static fn (Builder $query, string $order) => $query->orderBy('uses', $direction($order)))
            ->addColumn('code', static fn (AffiliatePromoCode $code): string => view('affiliates::portal._code-cell', ['code' => $code])->render())
            ->addColumn('discount', static fn (AffiliatePromoCode $code): string => '<span class="text-ink" dir="ltr">'.e($code->discountLabel()).'</span>')
            ->addColumn('uses', static fn (AffiliatePromoCode $code): string => '<span class="text-ink" dir="ltr">'.e($code->max_uses !== null ? $code->uses.' / '.$code->max_uses : (string) $code->uses).'</span>')
            ->addColumn('validity', static fn (AffiliatePromoCode $code): string => '<span class="text-xs text-ink-secondary">'.e($code->ends_at !== null
                ? __('affiliates::panel.links.until', ['date' => $code->ends_at->locale($locale)->translatedFormat('j M Y')])
                : __('affiliates::panel.links.no_end')).'</span>')
            ->addColumn('state', static fn (AffiliatePromoCode $code): string => view('affiliates::admin.partials.badge', [
                'tone' => $code->isUsable() ? 'success' : 'neutral',
                'label' => $code->isUsable() ? __('affiliates::panel.links.active') : __('affiliates::panel.links.inactive'),
            ])->render())
            ->rawColumns(['code', 'discount', 'uses', 'validity', 'state']);
    }
}
