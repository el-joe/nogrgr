<?php

namespace App\Modules\Integrations\DataTables;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Repositories\Contracts\GatewayLimitUsageRepositoryContract;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use App\Modules\Integrations\Services\Limits\GatewayLimitService;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * This month's platform-account volume per store and gateway (ADM-100c): volume, limit,
 * percent, state (ok / warned / blocked) and the admin "unblock" action.
 *
 * @extends BaseDataTable<GatewayLimitUsage>
 */
final class GatewayLimitUsagesDataTable extends BaseDataTable
{
    /**
     * @var array<string, int|null>|null
     */
    private ?array $limits = null;

    private bool $canManage = false;

    public function __construct(
        private readonly GatewayLimitUsageRepositoryContract $usages,
        private readonly PaymentGatewayRepositoryContract $gateways,
    ) {}

    public function abilities(bool $canManage): self
    {
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->usages->listing(GatewayLimitService::period());
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->usages->listing(GatewayLimitService::period(), $filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('store', fn (GatewayLimitUsage $usage): string => view('billing::admin._store-cell', ['tenant' => $usage->tenant])->render())
            ->editColumn('gateway', fn (GatewayLimitUsage $usage): string => e(PaymentGatewayCode::tryFrom($usage->gateway)?->brand() ?? $usage->gateway))
            ->editColumn('volume_minor', fn (GatewayLimitUsage $usage): string => '<bdi dir="ltr">'.e(Money::ofMinor($usage->volume_minor)->format()).'</bdi>')
            ->addColumn('limit', function (GatewayLimitUsage $usage): string {
                $limit = $this->limitOf($usage->gateway);

                return $limit === null ? e(__('integrations::admin.limits.no_limit')) : '<bdi dir="ltr">'.e(Money::ofMinor($limit)->format()).'</bdi>';
            })
            ->addColumn('percent', function (GatewayLimitUsage $usage): string {
                $percent = GatewayLimitService::percentOf($usage->volume_minor, $this->limitOf($usage->gateway));

                return $percent === null ? '—' : '<bdi dir="ltr">'.$percent.'%</bdi>';
            })
            ->addColumn('state', fn (GatewayLimitUsage $usage): string => view('integrations::admin.gateways.limits._state-cell', ['usage' => $usage])->render())
            ->addColumn('actions', fn (GatewayLimitUsage $usage): string => view('integrations::admin.gateways.limits._actions-cell', ['usage' => $usage, 'canManage' => $this->canManage])->render())
            ->rawColumns(['store', 'volume_minor', 'limit', 'percent', 'state', 'actions']);
    }

    private function limitOf(string $gateway): ?int
    {
        if ($this->limits === null) {
            $this->limits = [];

            foreach ($this->gateways->all() as $row) {
                /** @var PaymentGateway $row */
                $this->limits[$row->code->value] = $row->monthly_store_limit_minor;
            }
        }

        return $this->limits[$gateway] ?? null;
    }
}
