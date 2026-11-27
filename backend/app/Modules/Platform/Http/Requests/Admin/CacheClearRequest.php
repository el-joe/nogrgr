<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Enums\CacheAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cache tools (ADM-114): which cache to clear and, for store actions, which store.
 */
class CacheClearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('system.cache.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $storeActions = implode(',', array_map(static fn (CacheAction $action): string => $action->value, CacheAction::store()));

        return [
            'action' => ['required', 'string', Rule::enum(CacheAction::class)],
            'tenant_id' => ['nullable', 'required_if:action,'.$storeActions, 'integer', Rule::exists('tenants', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['tenant_id.required_if' => __('platform::cache_tools.rules.store_required')];
    }

    public function action(): CacheAction
    {
        return CacheAction::from((string) $this->validated('action'));
    }

    public function tenantId(): ?int
    {
        return $this->validated('tenant_id') !== null ? (int) $this->validated('tenant_id') : null;
    }
}
