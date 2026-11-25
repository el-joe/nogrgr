<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH {is_active: 0|1} from the list switch / row actions (activate / deactivate).
 */
class AdminStatusRequest extends FormRequest
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
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function status(): AccountStatus
    {
        return $this->boolean('is_active') ? AccountStatus::Active : AccountStatus::Suspended;
    }
}
