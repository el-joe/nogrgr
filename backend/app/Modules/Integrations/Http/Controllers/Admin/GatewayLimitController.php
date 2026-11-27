<?php

namespace App\Modules\Integrations\Http\Controllers\Admin;

use App\Modules\Integrations\DataTables\GatewayLimitUsagesDataTable;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Http\Requests\Admin\UpdateGatewayLimitsRequest;
use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Modules\Integrations\Services\Limits\GatewayLimitService;
use App\Modules\Integrations\Services\PaymentGatewayService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Integrations › Gateway limits (ADM-100c, decision #124): per gateway the
 * monthly volume limit each store may take through the platform account (warn at the
 * set %, block at 100 %), this month's usage per store (Yajra) and unblock.
 */
class GatewayLimitController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $gateways,
        private readonly GatewayLimitService $limits,
    ) {}

    public function index(Request $request): View
    {
        return view('integrations::admin.gateways.limits.index', [
            'gateways' => $this->gateways->all(),
            'period' => now()->translatedFormat('F Y'),
            'gatewayOptions' => collect(PaymentGatewayCode::cases())->mapWithKeys(static fn (PaymentGatewayCode $code): array => [$code->value => $code->brand()])->all(),
            'canManage' => (bool) $request->user('admin')?->can('integrations.manage'),
        ]);
    }

    public function data(Request $request, GatewayLimitUsagesDataTable $table): JsonResponse
    {
        return $table->abilities((bool) $request->user('admin')?->can('integrations.manage'))
            ->toJsonFromRequest($request, ['gateway', 'state', 'search']);
    }

    public function update(UpdateGatewayLimitsRequest $request): JsonResponse
    {
        $this->limits->updateLimits($request->limits());

        return JsonResponder::success(__('integrations::admin.limits.saved'), redirect: route('admin.integrations.gateways.limits'));
    }

    public function unblock(GatewayLimitUsage $usage): JsonResponse
    {
        $this->limits->unblock($usage);

        return JsonResponder::success(__('integrations::admin.limits.unblocked', ['store' => (string) ($usage->tenant?->name ?? '—')]));
    }
}
