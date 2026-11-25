<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin create / edit modal (ADM-110a): name, email (unique), role (an admin-guard
 * role), status, password (required on create, optional on edit; 12+ chars with a
 * number and a symbol).
 */
class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $admin = $this->route('admin');
        $creating = ! $admin instanceof Admin;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique(Admin::class, 'email')->ignore($creating ? null : $admin->getKey())],
            'role' => ['required', 'string', Rule::exists((string) config('permission.table_names.roles'), 'name')->where('guard_name', 'admin')],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:191', 'confirmed', self::passwordRule()],
        ];
    }

    public static function passwordRule(): Password
    {
        return Password::min(12)->letters()->numbers()->symbols();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('identity::admins.fields.name'),
            'email' => __('identity::admins.fields.email'),
            'role' => __('identity::admins.fields.role'),
            'status' => __('identity::admins.fields.status'),
            'password' => __('identity::admins.fields.password'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->validated('name')),
            'email' => (string) $this->validated('email'),
            'role' => (string) $this->validated('role'),
            'status' => (string) $this->validated('status'),
            'password' => $this->filled('password') ? (string) $this->validated('password') : null,
        ];
    }
}
