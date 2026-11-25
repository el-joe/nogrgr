<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\DataTables\AdminsDataTable;
use App\Modules\Identity\DTOs\AdminData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Http\Concerns\AnswersDomainErrors;
use App\Modules\Identity\Http\Requests\Admin\AdminRequest;
use App\Modules\Identity\Http\Requests\Admin\AdminStatusRequest;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Services\AdminService;
use App\Modules\Identity\Services\RoleService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Admin › System › Admins (ADM-110, Figma Admin Panel 80:90095) and the create / edit
 * modal (ADM-110a, 80:98485): list with role + status filters, activate / deactivate,
 * delete. Guards (self, last super-admin, super-admin only) live in AdminService.
 */
class AdminController extends Controller
{
    use AnswersDomainErrors;
    use RendersRemoteModals;

    public function __construct(
        private readonly AdminService $admins,
        private readonly RoleService $roles,
    ) {}

    public function index(): View
    {
        return view('identity::admin.admins.index', [
            'counts' => $this->admins->counts(),
            'rolesCount' => $this->roles->count(),
            'roleOptions' => $this->roles->options(),
        ]);
    }

    public function data(Request $request, AdminsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, AdminsDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        return $this->remoteModal($request, 'identity::admin.admins._form', [
            'admin' => null,
            'role' => null,
            'roleOptions' => $this->assignableRoles($request),
        ], $this->modalPage(__('identity::admins.create_title'), __('identity::admins.actions.invite')));
    }

    public function store(AdminRequest $request): JsonResponse
    {
        try {
            $admin = $this->admins->create(AdminData::fromArray($request->payload()), $this->actor($request));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('identity::admins.created', ['name' => $admin->name]));
    }

    public function edit(Request $request, Admin $admin): View
    {
        Gate::forUser($this->actor($request))->authorize('update', $admin);

        return $this->remoteModal($request, 'identity::admin.admins._form', [
            'admin' => $admin,
            'role' => $this->admins->roleOf($admin),
            'roleOptions' => $this->assignableRoles($request, $this->admins->roleOf($admin)),
        ], $this->modalPage(__('identity::admins.edit_title', ['name' => $admin->name]), __('identity::admins.actions.save')));
    }

    public function update(AdminRequest $request, Admin $admin): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('update', $admin);

        return $this->attempt(
            fn () => $this->admins->update($admin, AdminData::fromArray($request->payload()), $this->actor($request)),
            __('identity::admins.updated', ['name' => $admin->name]),
        );
    }

    public function toggle(AdminStatusRequest $request, Admin $admin): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('toggle', $admin);
        $status = $request->status();

        return $this->attempt(
            fn () => $this->admins->setStatus($admin, $status, $this->actor($request)),
            __($status === AccountStatus::Active ? 'identity::admins.activated' : 'identity::admins.deactivated', ['name' => $admin->name]),
        );
    }

    public function destroy(Request $request, Admin $admin): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('delete', $admin);

        return $this->attempt(
            fn () => $this->admins->delete($admin, $this->actor($request)),
            __('identity::admins.deleted', ['name' => $admin->name]),
        );
    }

    /**
     * Roles the signed-in admin may grant (super-admin only by a super-admin).
     *
     * @return array<string, string>
     */
    private function assignableRoles(Request $request, ?string $current = null): array
    {
        $options = $this->roles->options();

        if (! $this->actor($request)->isSuperAdmin()) {
            $options = array_filter($options, fn (string $label, string $name): bool => $name !== 'super-admin' || $name === $current, ARRAY_FILTER_USE_BOTH);
        }

        return $options;
    }

    private function actor(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }

    private function modalPage(string $title, string $submitLabel): RemoteModalPage
    {
        $index = route('admin.system.admins.index');

        return new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            pageModule: 'admin-admins',
            formId: 'admin-form',
            submitLabel: $submitLabel,
            breadcrumbs: [__('navigation.sections.system') => null, __('identity::admins.title') => $index, $title => null],
        );
    }
}
