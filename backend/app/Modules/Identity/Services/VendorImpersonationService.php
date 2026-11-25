<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Routing\VendorPanel;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin → vendor panel impersonation. Always opens the central-host mode
 * (`{central}/tenant/{store}`): it shares the admin's central session cookie and works
 * before the store has a custom domain. Audited with the impersonating admin id.
 */
class VendorImpersonationService extends BaseService
{
    public function __construct(
        private readonly VendorAuthService $auth,
        private readonly VendorStaffRepositoryContract $staff,
    ) {}

    /**
     * Signs the admin in as the store's owner and returns the panel URL to redirect to.
     */
    public function start(Admin $admin, Tenant $tenant, Request $request): string
    {
        $ownerId = $tenant->run(fn (): ?int => $this->staff->firstActiveOwner()?->id);

        if ($ownerId === null) {
            throw new NotFoundHttpException('This store has no active owner to impersonate.');
        }

        $this->auth->loginById($tenant, $ownerId, false, $request, $admin->id);

        return VendorPanel::centralUrl($tenant->slug);
    }
}
