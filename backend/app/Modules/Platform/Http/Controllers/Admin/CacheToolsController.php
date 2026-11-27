<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\OptionSearchRequest;
use App\Modules\Platform\Enums\CacheAction;
use App\Modules\Platform\Enums\CacheRunStatus;
use App\Modules\Platform\Http\Requests\Admin\CacheClearRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\CacheToolsService;
use App\Modules\Platform\Services\MaintenanceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › System tools › Cache tools (ADM-114): Main cache tab (ADM-114a) and Tenants
 * cache tab (ADM-114b, ?tab=tenants&store=ID). system.cache.view / .manage.
 */
class CacheToolsController extends Controller
{
    public const TABS = ['main', 'tenants'];

    public function __construct(private readonly CacheToolsService $tools) {}

    public function index(Request $request, TenantRepositoryContract $tenants): View
    {
        $tab = $request->query('tab');
        $tab = is_string($tab) && in_array($tab, self::TABS, true) ? $tab : 'main';
        $storeId = filter_var($request->query('store'), FILTER_VALIDATE_INT);
        $store = $storeId !== false ? $tenants->find($storeId) : null;
        $store = $store instanceof Tenant ? $store : null;

        return view('platform::admin.system.cache.index', [
            'tab' => $tab,
            'store' => $store,
            'runs' => $this->tools->lastRuns($store?->id !== null ? (int) $store->id : null),
            'storeCount' => $this->tools->storeCount(),
            'mainActions' => CacheAction::main(),
            'storeActions' => CacheAction::store(),
        ]);
    }

    public function stores(OptionSearchRequest $request, MaintenanceService $maintenance): JsonResponse
    {
        return response()->json($maintenance->storeOptions($request->term(), $request->page()));
    }

    public function clear(CacheClearRequest $request, TenantRepositoryContract $tenants): JsonResponse
    {
        $action = $request->action();
        $tenant = $request->tenantId() !== null ? $tenants->find($request->tenantId()) : null;
        $adminId = $request->user('admin')?->getAuthIdentifier();
        $run = $this->tools->run($action, $tenant instanceof Tenant ? $tenant : null, $adminId !== null ? (int) $adminId : null);

        return match ($run->status) {
            CacheRunStatus::Queued => JsonResponder::success(__('platform::cache_tools.queued', ['cache' => $action->label()]), ['status' => $run->status->value]),
            CacheRunStatus::Failed => JsonResponder::error(__('platform::cache_tools.failed', ['cache' => $action->label()]), 500),
            CacheRunStatus::Done => JsonResponder::success(__('platform::cache_tools.cleared', ['cache' => $action->label()]), ['status' => $run->status->value]),
        };
    }
}
