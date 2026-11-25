<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\DataTables\DnsRecordTemplatesDataTable;
use App\Modules\Platform\Http\Requests\Admin\DnsRecordTemplateRequest;
use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Services\DnsRecordTemplateService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Domains › DNS record templates (ADM-045b): table + create / edit modal and
 * delete. Permissions: vendors.domains.view (table) / vendors.domains.manage.
 */
class DnsRecordTemplateController extends Controller
{
    public function __construct(private readonly DnsRecordTemplateService $templates) {}

    public function data(Request $request, DnsRecordTemplatesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request);
    }

    public function store(DnsRecordTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create($request->toData());

        return JsonResponder::created(__('platform::domains.dns.created', ['type' => $template->type->label(), 'host' => $template->host]));
    }

    public function update(DnsRecordTemplateRequest $request, DnsRecordTemplate $dnsRecordTemplate): JsonResponse
    {
        $template = $this->templates->update($dnsRecordTemplate, $request->toData());

        return JsonResponder::success(__('platform::domains.dns.updated', ['type' => $template->type->label(), 'host' => $template->host]));
    }

    public function destroy(DnsRecordTemplate $dnsRecordTemplate): JsonResponse
    {
        $this->templates->delete($dnsRecordTemplate);

        return JsonResponder::success(__('platform::domains.dns.deleted', ['type' => $dnsRecordTemplate->type->label(), 'host' => $dnsRecordTemplate->host]));
    }
}
