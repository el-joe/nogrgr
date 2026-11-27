<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Role create / edit (ADM-111a): unique name per guard + permission names from the
 * admin catalog only.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Guard whose roles and permission catalog this request edits.
     */
    protected function guard(): string
    {
        return 'admin';
    }

    /**
     * The form always posts `permissions` (an empty hidden field when nothing is ticked).
     */
    protected function prepareForValidation(): void
    {
        $permissions = $this->input('permissions');

        $this->merge(['permissions' => is_array($permissions) ? array_values(array_filter($permissions, fn (mixed $value): bool => $value !== '' && $value !== null)) : []]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60', 'not_regex:/[<>]/',
                Rule::unique((string) config('permission.table_names.roles'), 'name')
                    ->where('guard_name', $this->guard())
                    ->ignore($role instanceof Role ? $role->getKey() : (is_numeric($role) ? (int) $role : null)),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalog::assignable($this->guard()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('identity::roles.fields.name'),
            'permissions' => __('identity::roles.fields.permissions'),
            'permissions.*' => __('identity::roles.fields.permissions'),
        ];
    }

    /**
     * @return array{name: string, permissions: list<string>}
     */
    public function payload(): array
    {
        /** @var list<string> $permissions */
        $permissions = array_values((array) $this->validated('permissions', []));

        return [
            'name' => trim((string) $this->validated('name')),
            'permissions' => $permissions,
        ];
    }
}
