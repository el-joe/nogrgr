<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use App\Modules\Catalog\Enums\RequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPriorityRequest extends FormRequest
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
        return ['priority' => ['required', Rule::enum(RequestPriority::class)]];
    }

    public function priority(): RequestPriority
    {
        return RequestPriority::from((string) $this->validated('priority'));
    }
}
