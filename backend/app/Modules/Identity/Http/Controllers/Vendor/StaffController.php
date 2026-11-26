<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DataTables\VendorStaffDataTable;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Exceptions\VendorStaffRuleException;
use App\Modules\Identity\Http\Concerns\AnswersDomainErrors;
use App\Modules\Identity\Http\Requests\Vendor\StaffRequest;
use App\Modules\Identity\Http\Requests\Vendor\StaffStatusRequest;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Services\RoleService;
use App\Modules\Identity\Services\VendorStaffService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Staff (VEN-076, Figma Tenant Panel 90:115176) and the invite /
 * edit modal (VEN-076a): list with role + status filters, invite, edit, activate /
 * deactivate, resend invitation, delete. Rules (owner untouchable, self, quota) live
 * in VendorStaffService; everything runs in the store's tenant DB.
 */
class StaffController extends Controller
{
    use AnswersDomainErrors;
    use RendersRemoteModals;

    public function __construct(
        private readonly VendorStaffService $staff,
        private readonly RoleService $roles,
    ) {}

    public function index(): View
    {
        $limit = $this->staff->limit();
        $counts = $this->staff->counts();

        return view('identity::vendor.staff.index', [
            'counts' => $counts,
            'limit' => $limit,
            'limitReached' => $limit !== null && $counts['all'] >= $limit,
            'roleOptions' => $this->roles->options(Guard::Vendor->value),
            'statuses' => AccountStatus::options(),
        ]);
    }

    public function data(Request $request, VendorStaffDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, VendorStaffDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        return $this->form($request, null);
    }

    public function store(StaffRequest $request): JsonResponse
    {
        try {
            $member = $this->staff->invite($request->toData(), $this->actor($request));
        } catch (VendorStaffRuleException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('identity::staff.invited', ['name' => $member->name, 'email' => $member->email]));
    }

    public function show(Request $request, int $staff): View
    {
        $member = $this->staff->find($staff);

        return $this->remoteModal($request, 'identity::vendor.staff._show', [
            'member' => $member,
            'role' => $this->staff->roleOf($member),
            'locked' => $this->staff->isLocked($member),
            'permissions' => $member->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ], $this->modalPage($member->name, null));
    }

    public function edit(Request $request, int $staff): View
    {
        $member = $this->staff->find($staff);
        abort_if($this->staff->isLocked($member) || $member->is($this->actor($request)), 403);

        return $this->form($request, $member);
    }

    public function update(StaffRequest $request, int $staff): JsonResponse
    {
        $member = $this->staff->find($staff);

        return $this->attempt(
            fn () => $this->staff->update($member, $request->toData(), $this->actor($request)),
            __('identity::staff.updated', ['name' => $request->toData()->name]),
        );
    }

    public function toggle(StaffStatusRequest $request, int $staff): JsonResponse
    {
        $member = $this->staff->find($staff);

        return $this->attempt(
            fn () => $this->staff->setActive($member, $request->active(), $this->actor($request)),
            __($request->active() ? 'identity::staff.activated' : 'identity::staff.deactivated', ['name' => $member->name]),
        );
    }

    public function resend(Request $request, int $staff): JsonResponse
    {
        $member = $this->staff->find($staff);

        return $this->attempt(
            fn () => $this->staff->resendInvitation($member, $this->actor($request)),
            __('identity::staff.resent', ['email' => $member->email]),
        );
    }

    public function destroy(Request $request, int $staff): JsonResponse
    {
        $member = $this->staff->find($staff);

        return $this->attempt(
            fn () => $this->staff->delete($member, $this->actor($request)),
            __('identity::staff.deleted', ['name' => $member->name]),
        );
    }

    private function form(Request $request, ?VendorStaff $member): View
    {
        $title = $member !== null ? __('identity::staff.edit_title', ['name' => $member->name]) : __('identity::staff.invite_title');
        $options = array_filter(
            $this->roles->options(Guard::Vendor->value),
            static fn (string $name): bool => ! SystemRole::lockedFor($name, Guard::Vendor->value),
            ARRAY_FILTER_USE_KEY,
        );

        return $this->remoteModal($request, 'identity::vendor.staff._form', [
            'member' => $member,
            'title' => $title,
            'role' => $member !== null ? $this->staff->roleOf($member) : null,
            'roleOptions' => $options,
        ], $this->modalPage($title, $member !== null ? __('identity::staff.save') : __('identity::staff.send_invite')));
    }

    private function modalPage(string $title, ?string $submitLabel): RemoteModalPage
    {
        $index = vendor_route('settings.staff.index');

        return new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-staff',
            formId: $submitLabel !== null ? 'vendor-staff-form' : null,
            submitLabel: $submitLabel,
            breadcrumbs: [__('navigation.admin.settings') => null, __('identity::staff.title') => $index, $title => null],
        );
    }

    private function actor(Request $request): VendorStaff
    {
        /** @var VendorStaff $actor */
        $actor = $request->user('vendor');

        return $actor;
    }
}
