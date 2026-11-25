<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\DataTables\RolesDataTable;
use App\Modules\Identity\DTOs\RoleData;
use App\Modules\Identity\Http\Concerns\AnswersDomainErrors;
use App\Modules\Identity\Http\Requests\Admin\RoleRequest;
use App\Modules\Identity\Services\AdminService;
use App\Modules\Identity\Services\RoleService;
use App\Modules\Identity\Support\PermissionMatrix;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Admin › System › Roles (ADM-111) and the role editor with the permission matrix
 * grouped by module (ADM-111a, Figma Admin Panel 80:91028). "Duplicate role" opens the
 * editor pre-filled (?from=<id>). super-admin is shown read-only; assigned roles
 * cannot be deleted.
 */
class RoleController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly RoleService $roles,
        private readonly AdminService $admins,
    ) {}

    public function index(): View
    {
        return view('identity::admin.roles.index', [
            'rolesCount' => $this->roles->count(),
            'adminsCount' => $this->admins->counts()['all'],
        ]);
    }

    public function data(Request $request, RolesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, RolesDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        $source = $request->integer('from') > 0 ? $this->roles->find($request->integer('from')) : null;

        return $this->editor(null, $source !== null ? $this->roles->permissionsOf($source) : [], $source !== null
            ? __('identity::roles.copy_name', ['name' => $source->name])
            : '');
    }

    public function store(RoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roles->create(RoleData::fromArray($request->payload()));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('identity::roles.created', ['name' => $role->name]), redirect: route('admin.system.roles.index'));
    }

    public function edit(Role $role): View
    {
        abort_unless($role->guard_name === 'admin', 404);

        return $this->editor($role, $this->roles->permissionsOf($role), $role->name);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'admin', 404);

        return $this->attempt(
            fn () => $this->roles->update($role, RoleData::fromArray($request->payload())),
            __('identity::roles.updated', ['name' => $request->payload()['name']]),
            route('admin.system.roles.index'),
        );
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'admin', 404);

        return $this->attempt(
            fn () => $this->roles->delete($role),
            __('identity::roles.deleted', ['name' => $role->name]),
        );
    }

    /**
     * @param  list<string>  $granted
     */
    private function editor(?Role $role, array $granted, string $name): View
    {
        return view('identity::admin.roles.form', [
            'role' => $role,
            'name' => $name,
            'granted' => $granted,
            'locked' => $role !== null && $this->roles->isLocked($role),
            'usersCount' => $role !== null ? $this->roles->usersCount($role) : 0,
            'matrix' => PermissionMatrix::for('admin'),
            'rolesCount' => $this->roles->count(),
            'adminsCount' => $this->admins->counts()['all'],
        ]);
    }
}
