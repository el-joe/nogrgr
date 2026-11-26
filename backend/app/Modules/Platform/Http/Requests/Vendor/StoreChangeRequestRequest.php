<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\DTOs\StoreChangeRequestData;
use App\Modules\Platform\Services\StoreGeneralSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * VEN-071a / 071b change request modal: "add" from the countries / categories the store
 * does not sell yet, "remove" from the ones it sells, at least one of them, optional reason.
 * The type comes from the route ({type} = countries | categories).
 */
class StoreChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function type(): TenantChangeType
    {
        return TenantChangeType::from((string) $this->route('type'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = app(StoreGeneralSettingsService::class);
        $type = $this->type();

        return [
            'add' => ['nullable', 'array', 'max:100'],
            'add.*' => ['integer', 'distinct', Rule::in($service->addableIds($type))],
            'remove' => ['nullable', 'array', 'max:100'],
            'remove.*' => ['integer', 'distinct', Rule::in($service->currentIds($type))],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->ids('add') === [] && $this->ids('remove') === []) {
                    $validator->errors()->add('add', __('platform::vendor_general.errors.nothing_selected'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $type = $this->type()->value;

        return [
            'add' => __('platform::vendor_general.modal.'.$type.'.add'),
            'add.*' => __('platform::vendor_general.modal.'.$type.'.add'),
            'remove' => __('platform::vendor_general.modal.'.$type.'.remove'),
            'remove.*' => __('platform::vendor_general.modal.'.$type.'.remove'),
            'reason' => __('platform::vendor_general.modal.reason'),
        ];
    }

    public function toData(): StoreChangeRequestData
    {
        $staff = $this->user('vendor');
        $reason = trim((string) $this->validated('reason'));

        return new StoreChangeRequestData(
            type: $this->type(),
            add: $this->ids('add'),
            remove: $this->ids('remove'),
            reason: $reason !== '' ? $reason : null,
            submittedBy: $staff instanceof VendorStaff ? (string) $staff->name : null,
        );
    }

    /**
     * @return list<int>
     */
    private function ids(string $key): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) $this->input($key, []), 'is_numeric'))));
    }
}
