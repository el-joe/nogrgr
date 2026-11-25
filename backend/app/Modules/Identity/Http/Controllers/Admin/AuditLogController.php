<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\DataTables\AuditEventsDataTable;
use App\Modules\Identity\DTOs\AuditFilterData;
use App\Modules\Identity\Http\Requests\Admin\AuditExportRequest;
use App\Modules\Identity\Http\Requests\Admin\OptionSearchRequest;
use App\Modules\Identity\Services\AuditLogService;
use App\Modules\Identity\Support\AuditPresenter;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Audit log (ADM-112, Figma Admin Panel 80:91969) and the event detail with
 * the before/after diff (ADM-112a, 80:98485). Reads Platform's audit trail
 * (decision #20); filters by actor, tenant, event and period; CSV export.
 */
class AuditLogController extends Controller
{
    use RendersRemoteModals;

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $tenant = $request->integer('tenant');

        return view('identity::admin.audit.index', [
            'adminOptions' => $this->audit->adminOptions(),
            'eventOptions' => $this->audit->eventOptions(),
            'tenantSelected' => $tenant > 0 ? [$tenant => (string) $this->audit->tenantLabel($tenant)] : [],
        ]);
    }

    public function data(Request $request, AuditEventsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, AuditEventsDataTable::FILTERS);
    }

    public function show(Request $request, int $event): View
    {
        $record = $this->audit->find($event);
        abort_if($record === null, 404);
        $title = AuditPresenter::actionLabel($record->action);
        $index = route('admin.system.audit.index');

        return $this->remoteModal($request, 'identity::admin.audit._show', [
            'event' => $record,
            'actor' => $this->audit->actorLabel($record),
            'tenant' => $this->audit->tenantLabel($record->tenant_id),
            'time' => $this->audit->localTime($record->created_at),
            'diff' => AuditPresenter::diff($record),
            'context' => AuditPresenter::context($record),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            pageModule: 'admin-audit',
            breadcrumbs: [__('navigation.sections.system') => null, __('identity::audit.title') => $index, $title => null],
        ));
    }

    public function tenants(OptionSearchRequest $request): JsonResponse
    {
        return response()->json($this->audit->tenantOptions($request->term(), $request->page()));
    }

    public function export(AuditExportRequest $request): StreamedResponse
    {
        $filters = AuditFilterData::fromFilters($request->validated());

        return response()->streamDownload(
            $this->audit->export($filters),
            'audit-log-'.now()->format('Y-m-d-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
