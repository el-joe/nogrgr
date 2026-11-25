<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Status change of a product / manufacturing / brand request (status of that kind only).
 */
class RequestStatusRequest extends FormRequest
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
        $values = match (RequestKind::fromRouteName($this->route()?->getName())) {
            RequestKind::Product => ProductRequestStatus::values(),
            RequestKind::Manufacturing => ManufacturingRequestStatus::values(),
            RequestKind::Brand => BrandRequestStatus::values(),
            null => [],
        };

        return ['status' => ['required', 'string', Rule::in($values)]];
    }

    public function status(): string
    {
        return (string) $this->validated('status');
    }
}
