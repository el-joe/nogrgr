<?php

namespace App\Modules\Inventory\Http\Requests\StoreApi;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Inventory\DTOs\StockAlertSubscribeData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /store-api/v1/products/{storeProduct}/stock-alerts: a guest gives an e-mail; a
 * signed-in customer (session or bearer token) needs none.
 */
class StockAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [StoreApiCustomer::resolve($this) === null ? 'required' : 'nullable', 'string', 'email:rfc', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __st('product.stock_alert.email')];
    }

    public function toData(): StockAlertSubscribeData
    {
        $email = $this->validated('email');

        return new StockAlertSubscribeData(is_string($email) && $email !== '' ? $email : null, app()->getLocale());
    }
}
