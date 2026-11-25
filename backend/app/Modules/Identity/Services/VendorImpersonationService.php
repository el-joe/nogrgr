<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Exceptions\ImpersonationLinkExpiredException;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Routing\VendorPanel;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin → vendor panel impersonation (VEN-005), in two steps:
 *
 *  1. start(): the admin (already audited with a reason by VendorService) gets a
 *     one-time, signed accept link valid for LINK_TTL_SECONDS.
 *  2. accept(): consumes the token, signs in as the store owner on the central-host
 *     panel (`{central}/tenant/{store}`) for SESSION_MINUTES; the panel shows the
 *     "impersonating" banner with a countdown and End session (leave()).
 *
 * The session ends by itself once the time is up (EnsureVendorSessionTenant).
 * Start, end and every action in between are audited with the admin id.
 */
class VendorImpersonationService extends BaseService
{
    public const LINK_TTL_SECONDS = 120;

    public const SESSION_MINUTES = 30;

    public const SESSION_EXPIRES = 'vendor_panel.impersonation_expires_at';

    private const CACHE_PREFIX = 'vendor-impersonation:';

    public function __construct(
        private readonly VendorAuthService $auth,
        private readonly VendorStaffRepositoryContract $staff,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
        private readonly Auditor $auditor,
    ) {}

    /**
     * Issues the one-time accept link for the store's owner account.
     */
    public function start(Admin $admin, Tenant $tenant, Request $request): string
    {
        $ownerId = $tenant->run(fn (): ?int => $this->staff->firstActiveOwner()?->id);

        if ($ownerId === null) {
            throw new NotFoundHttpException('This store has no active owner to impersonate.');
        }

        $token = Str::random(64);
        Cache::put(self::CACHE_PREFIX.hash('sha256', $token), [
            'tenant_id' => $tenant->id,
            'staff_id' => $ownerId,
            'admin_id' => $admin->id,
        ], self::LINK_TTL_SECONDS);

        return URL::temporarySignedRoute('central.tenant.impersonate', now()->addSeconds(self::LINK_TTL_SECONDS), ['token' => $token]);
    }

    /**
     * Consumes the token and opens the panel. Returns the dashboard URL.
     *
     * @throws ImpersonationLinkExpiredException when the link expired or was already used
     */
    public function accept(string $token, Request $request): string
    {
        $payload = Cache::pull(self::CACHE_PREFIX.hash('sha256', $token));
        $tenant = is_array($payload) ? $this->tenants->find((int) $payload['tenant_id']) : null;

        if (! is_array($payload) || ! $tenant instanceof Tenant) {
            throw new ImpersonationLinkExpiredException;
        }

        $this->auth->loginById($tenant, (int) $payload['staff_id'], false, $request, (int) $payload['admin_id']);
        $request->session()->put(self::SESSION_EXPIRES, now()->addMinutes(self::SESSION_MINUTES)->getTimestamp());

        return VendorPanel::centralUrl($tenant->slug);
    }

    /**
     * Banner data for the current request, or null when nobody is impersonating.
     *
     * @return array{admin_name: string, ends_at: Carbon, minutes_left: int, leave_url: string}|null
     */
    public function current(Request $request): ?array
    {
        if (! $request->hasSession() || ! Auth::guard('vendor')->check()) {
            return null;
        }

        $adminId = $request->session()->get(VendorPanel::SESSION_IMPERSONATOR);

        if (! is_numeric($adminId)) {
            return null;
        }

        $endsAt = $this->endsAt($request) ?? now()->addMinutes(self::SESSION_MINUTES);
        $admin = $this->admins->find((int) $adminId);

        return [
            'admin_name' => $admin instanceof Admin ? $admin->name : (string) __('identity::vendor_auth.impersonation.support'),
            'ends_at' => $endsAt,
            'minutes_left' => max(1, (int) ceil(now()->diffInSeconds($endsAt, false) / 60)),
            'leave_url' => vendor_route('impersonation.leave'),
        ];
    }

    public function isExpired(Request $request): bool
    {
        $endsAt = $this->endsAt($request);

        return $request->session()->has(VendorPanel::SESSION_IMPERSONATOR) && $endsAt !== null && $endsAt->isPast();
    }

    /**
     * Ends the impersonation (End session, or the time ran out): audited, then the vendor
     * session closes while the admin session stays. Returns the admin vendor profile URL.
     */
    public function leave(Request $request, bool $expired = false): string
    {
        $tenant = tenant();
        $adminId = $request->session()->get(VendorPanel::SESSION_IMPERSONATOR);
        $staff = Auth::guard('vendor')->user();

        if (is_numeric($adminId)) {
            $this->auditor->record(AuditAction::ImpersonationEnded, $staff instanceof VendorStaff ? $staff : null, [
                'impersonator_admin_id' => (int) $adminId,
                'reason' => $expired ? 'expired' : 'ended',
            ]);
        }

        $this->auth->logout($request);

        return $tenant instanceof Tenant && Route::has('admin.vendors.show')
            ? route('admin.vendors.show', $tenant->id)
            : route('central.tenant.login');
    }

    private function endsAt(Request $request): ?Carbon
    {
        $timestamp = $request->session()->get(self::SESSION_EXPIRES);

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
