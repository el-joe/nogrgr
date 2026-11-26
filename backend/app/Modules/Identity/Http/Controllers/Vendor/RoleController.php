<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DataTables\VendorRolesDataTable;
use App\Modules\Identity\DTOs\RoleData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Concerns\AnswersDomainErrors;
use App\Modules\Identity\Http\Requests\Vendor\VendorRoleRequest;
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
 * Vendor › Settings › Roles & permissions (VEN-077) and the role editor (VEN-077a,
 * Figma Tenant Panel 90:115822): store roles on spatie/permission (tenant DB, guard
 * "vendor") with the PermissionCatalog tenant matrix grouped by panel area. The
 * built-in "owner" role is read-only (kept complete by `permissions:sync`); a role
 * still assigned to staff cannot be deleted. Deny by default.
 */
class RoleController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(private readonly RoleService $roles) {}

    public function index(): View
    {
        return view('identity::vendor.roles.index', [
            'rolesCount' => $this->roles->count(Guard::Vendor->value),
        ]);
    }

    public function data(Request $request, VendorRolesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, VendorRolesDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        $source = $request->integer('from') > 0 ? $this->roles->find($request->integer('from'), Guard::Vendor->value) : null;

        return $this->editor(null, $source !== null ? $this->roles->permissionsOf($source) : [], $source !== null
            ? __('identity::roles.copy_name', ['name' => $source->name])
            : '');
    }

    public function store(VendorRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roles->create(RoleData::fromArray($request->payload()), Guard::Vendor->value);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('identity::roles.created', ['name' => $role->name]), redirect: vendor_route('settings.roles.index'));
    }

    public function edit(int $role): View
    {
        $current = $this->role($role);

        return $this->editor($current, $this->roles->permissionsOf($current), $current->name);
    }

    public function update(VendorRoleRequest $request, int $role): JsonResponse
    {
        $current = $this->role($role);

        return $this->attempt(
            fn () => $this->roles->update($current, RoleData::fromArray($request->payload())),
            __('identity::roles.updated', ['name' => $request->payload()['name']]),
            vendor_route('settings.roles.index'),
        );
    }

    public function destroy(int $role): JsonResponse
    {
        $current = $this->role($role);

        return $this->attempt(
            fn () => $this->roles->delete($current),
            __('identity::roles.deleted', ['name' => $current->name]),
        );
    }

    private function role(int $id): Role
    {
        $role = $this->roles->find($id, Guard::Vendor->value);
        abort_if($role === null, 404);

        return $role;
    }

    /**
     * @param  list<string>  $granted
     */
    private function editor(?Role $role, array $granted, string $name): View
    {
        return view('identity::vendor.roles.form', [
            'role' => $role,
            'name' => $name,
            'granted' => $granted,
            'locked' => $role !== null && $this->roles->isLocked($role),
            'usersCount' => $role !== null ? $this->roles->usersCount($role) : 0,
            'matrix' => PermissionMatrix::for(Guard::Vendor->value),
        ]);
    }
}
