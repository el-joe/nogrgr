<?php

namespace App\Modules\Ai\DataTables;

use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Modules\Ai\Services\StoreTranslation\AiTranslationOverviewService;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Settings › AI translation › Run history (VEN-074): the store's full-store
 * translation runs (store_translation_runs) and per-product "Translate with AI" calls
 * (ai_usage_logs, feature product_translation) in one server-side table: language,
 * scope, status, items done, failed, tokens, started, finished and Run again / Retry
 * on the latest run of a language.
 *
 * @extends BaseDataTable<StoreTranslationRun>
 */
final class StoreTranslationHistoryDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'scope'];

    private ?Tenant $tenant = null;

    private bool $canManage = false;

    /**
     * @var array<string, int> language code => id of its latest run
     */
    private array $latest = [];

    /**
     * @var array<string, string>|null
     */
    private ?array $names = null;

    /**
     * @var array<int, string>
     */
    private array $products = [];

    public function __construct(
        private readonly StoreTranslationRunRepositoryContract $runs,
        private readonly AiUsageLogRepositoryContract $usage,
        private readonly AiTranslationOverviewService $overview,
    ) {}

    public function forTenant(Tenant $tenant, bool $canManage): self
    {
        $this->tenant = $tenant;
        $this->canManage = $canManage;
        $this->latest = $this->runs->latestByLanguage((int) $tenant->id)->map(static fn (StoreTranslationRun $run): int => $run->id)->all();

        return $this;
    }

    protected function query(): Builder
    {
        return $this->listing();
    }

    /**
     * @return Builder<StoreTranslationRun>
     */
    private function listing(?string $scope = null): Builder
    {
        $tenantId = (int) $this->tenant?->id;
        $rows = match ($scope) {
            'store' => $this->runs->historyRows($tenantId),
            'product' => $this->usage->productTranslationRows($tenantId),
            default => $this->runs->historyRows($tenantId)->unionAll($this->usage->productTranslationRows($tenantId)),
        };

        return $this->runs->historyListing($rows);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $scope = in_array($filters['scope'] ?? null, ['store', 'product'], true) ? $filters['scope'] : null;
        $query = $this->listing($scope);
        $status = TranslationRunStatus::tryFrom((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($search !== '') {
            $codes = $this->overview->languageCodesMatching($search);
            $query->where(static function (Builder $where) use ($codes, $search): void {
                $where->whereIn('language_code', $codes === [] ? ['-'] : $codes)
                    ->orWhere('subject', 'like', '%'.addcslashes($search, '%_\\').'%');
            });
        }

        return $query;
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $numberLocale = Locale::numberLocale();
        $number = static fn (?int $value): string => $value === null ? '—' : (string) Number::format($value, locale: $numberLocale);
        $date = static fn (mixed $value): string => $value === null ? '—' : e(Carbon::parse($value)->translatedFormat('j M, H:i'));

        return $table
            ->filter(static fn (): null => null)
            ->setRowId(static fn (StoreTranslationRun $row): string => $row->getAttribute('kind').'-'.$row->id)
            ->addColumn('language', fn (StoreTranslationRun $row): string => '<span class="font-semibold text-ink">'.e($row->getAttribute('kind') === 'product'
                ? __('ai::ai_translation.history.store_languages')
                : ($this->languageNames()[$row->language_code] ?? strtoupper((string) $row->language_code))).'</span>')
            ->addColumn('scope', fn (StoreTranslationRun $row): string => e($this->scope($row)))
            ->editColumn('status', static fn (StoreTranslationRun $row): string => view('ai::vendor.ai-translation._status', ['status' => $row->status, 'context' => 'history'])->render())
            ->editColumn('done_items', static fn (StoreTranslationRun $row): string => '<bdi dir="ltr">'.$number($row->getAttribute('kind') === 'product' ? null : $row->done_items).'</bdi>')
            ->editColumn('failed_items', static fn (StoreTranslationRun $row): string => $row->getAttribute('kind') === 'product'
                ? '—'
                : '<bdi dir="ltr" class="'.($row->failed_items > 0 ? 'font-semibold text-danger' : '').'">'.$number($row->failed_items).'</bdi>')
            ->editColumn('tokens', static fn (StoreTranslationRun $row): string => '<bdi dir="ltr">'.$number((int) $row->getAttribute('tokens')).'</bdi>')
            ->editColumn('started_at', static fn (StoreTranslationRun $row): string => $date($row->getRawOriginal('started_at')))
            ->editColumn('finished_at', static fn (StoreTranslationRun $row): string => $date($row->getRawOriginal('finished_at')))
            ->addColumn('actions', fn (StoreTranslationRun $row): string => view('ai::vendor.ai-translation._actions', [
                'row' => $row,
                'isLatest' => $row->getAttribute('kind') === 'run' && ($this->latest[$row->language_code] ?? null) === $row->id,
                'canManage' => $this->canManage,
                'name' => $this->languageNames()[$row->language_code] ?? strtoupper((string) $row->language_code),
            ])->render())
            ->orderColumn('started_at', static fn (Builder $query, string $order) => $query->orderBy('sort_at', $order === 'asc' ? 'asc' : 'desc')->orderBy('id', $order === 'asc' ? 'asc' : 'desc'))
            ->orderColumn('finished_at', static fn (Builder $query, string $order) => $query->orderBy('finished_at', $order === 'asc' ? 'asc' : 'desc'))
            ->orderColumn('done_items', static fn (Builder $query, string $order) => $query->orderBy('done_items', $order === 'asc' ? 'asc' : 'desc'))
            ->orderColumn('failed_items', static fn (Builder $query, string $order) => $query->orderBy('failed_items', $order === 'asc' ? 'asc' : 'desc'))
            ->orderColumn('tokens', static fn (Builder $query, string $order) => $query->orderBy('tokens', $order === 'asc' ? 'asc' : 'desc'))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $order === 'asc' ? 'asc' : 'desc'))
            ->rawColumns(['language', 'status', 'done_items', 'failed_items', 'tokens', 'actions']);
    }

    private function scope(StoreTranslationRun $row): string
    {
        if ($row->getAttribute('kind') !== 'product') {
            return (string) __('ai::ai_translation.history.scope_'.($row->getRawOriginal('trigger') === 'delta' ? 'delta' : 'store'));
        }

        $subject = (string) $row->getAttribute('subject');
        $id = (int) substr($subject, 14);

        if (! array_key_exists($id, $this->products) && $this->tenant !== null) {
            $this->products += $this->overview->productNames($this->tenant, [$subject]);
        }

        return (string) __('ai::ai_translation.history.scope_product', ['product' => $this->products[$id] ?? '#'.$id]);
    }

    /**
     * @return array<string, string>
     */
    private function languageNames(): array
    {
        return $this->names ??= $this->overview->languageNames();
    }
}
