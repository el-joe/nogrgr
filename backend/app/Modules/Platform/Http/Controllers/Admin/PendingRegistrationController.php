<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Platform\DataTables\PendingRegistrationsDataTable;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Services\VendorRegistrationService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Vendors › Pending registrations (ADM-042): sign-ups that have not finished
 * payment or store setup, their detail, "resend completion link" and delete.
 * Permissions: vendors.view / vendors.manage (resend) / vendors.delete.
 */
class PendingRegistrationController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registrations,
        private readonly PlanRepositoryContract $plans,
    ) {}

    public function index(): View
    {
        return view('platform::admin.registrations.index', [
            'counts' => $this->registrations->counts(),
            'statuses' => RegistrationStatus::pendingOptions(),
            'plans' => $this->plans->options(),
        ]);
    }

    public function data(Request $request, PendingRegistrationsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, PendingRegistrationsDataTable::FILTERS);
    }

    public function show(VendorRegistration $registration): View
    {
        $registration->loadMissing('plan:id,name');

        return view('platform::admin.registrations.show', [
            'registration' => $registration,
            'linkHours' => $this->registrations->linkLifetimeHours(),
        ]);
    }

    public function resend(VendorRegistration $registration): JsonResponse
    {
        $this->registrations->sendLink($registration);

        return JsonResponder::success(__('platform::registrations.actions.resent', ['email' => $registration->email]));
    }

    public function destroy(Request $request, VendorRegistration $registration): JsonResponse
    {
        $email = $registration->email;
        $this->registrations->delete($registration);

        return JsonResponder::success(
            __('platform::registrations.actions.deleted', ['email' => $email]),
            redirect: $request->boolean('redirect') ? route('admin.vendors.pending') : null,
        );
    }
}
