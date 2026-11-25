<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Services\VendorRegistrationService;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Provisioning / loading page (WEB-020) after the store step, and the signed JSON
 * status it polls until the store is live (or provisioning failed).
 */
class RegistrationProvisioningController extends Controller
{
    public function __construct(private readonly VendorRegistrationService $registrations) {}

    public function show(Request $request, VendorRegistration $registration): View
    {
        // Same as the `signed` middleware, but the language switch (?lang=) keeps the link valid.
        abort_unless(URL::hasValidSignature($request, true, ['lang']), 403);

        $tenant = $this->tenantOr404($registration);

        return view('platform::central.register.provisioning', [
            'tenant' => $tenant,
            'state' => $this->payload($tenant),
            'statusUrl' => $this->registrations->provisioningStatusUrl($registration),
            'storeHost' => TenantSlug::subdomainHost($tenant->slug),
            'customDomain' => $registration->custom_domain,
            'supportEmail' => (string) config('mail.from.address'),
        ]);
    }

    public function status(VendorRegistration $registration): JsonResponse
    {
        return response()->json($this->payload($this->tenantOr404($registration)));
    }

    /**
     * @return array{status: string, label: string, done: bool, failed: bool, redirect: string|null}
     */
    private function payload(Tenant $tenant): array
    {
        $done = $tenant->status->isOperational();

        return [
            'status' => $tenant->status->value,
            'label' => $tenant->status->label(),
            'done' => $done,
            'failed' => $tenant->status === TenantStatus::Failed,
            'redirect' => $done ? route('central.tenant.login', ['store' => $tenant->slug]) : null,
        ];
    }

    private function tenantOr404(VendorRegistration $registration): Tenant
    {
        $tenant = $this->registrations->provisionedTenant($registration);
        abort_if($tenant === null, 404);

        return $tenant;
    }
}
