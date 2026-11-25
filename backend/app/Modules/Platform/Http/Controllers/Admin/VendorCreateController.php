<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Http\Requests\Admin\VendorCreateRequest;
use App\Modules\Platform\Http\Requests\Admin\VendorSlugCheckRequest;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Services\VendorRegistrationService;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Vendors › Create vendor (ADM-040a): 4-step wizard posting once to store(),
 * which creates the pending store and queues provisioning. slugCheck() answers the
 * live sub-domain availability check. Permission: vendors.manage.
 */
class VendorCreateController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly VendorRegistrationService $registrations,
    ) {}

    public function create(PlanRepositoryContract $plans, CountryService $countries): View
    {
        return view('platform::admin.vendors.create', [
            'plans' => $plans->options(activeOnly: true),
            'terms' => BillingTerm::options(),
            'assignments' => PlanAssignment::options(),
            'countries' => $countries->options(),
            'baseDomain' => (string) config('tenancy.base_domain'),
        ]);
    }

    public function store(VendorCreateRequest $request): JsonResponse
    {
        $tenant = $this->provisioning->provision(ProvisionTenantData::fromArray($request->payload()));

        return JsonResponder::created(
            __('platform::vendors.create.created', ['name' => $tenant->name]),
            ['id' => $tenant->id],
            route('admin.vendors.show', $tenant->id),
        );
    }

    public function slugCheck(VendorSlugCheckRequest $request): JsonResponse
    {
        $slug = $request->slug();
        $valid = TenantSlug::isValid($slug);
        $available = $valid && $this->registrations->slugAvailable($slug);

        return new JsonResponse([
            'slug' => $slug,
            'host' => TenantSlug::subdomainHost($slug),
            'available' => $available,
            'message' => match (true) {
                ! $valid => __('platform::validation.tenant_slug'),
                ! $available => __('platform::vendors.create.slug_taken'),
                default => __('platform::vendors.create.slug_available', ['host' => TenantSlug::subdomainHost($slug)]),
            },
        ]);
    }
}
