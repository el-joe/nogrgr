<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use App\Modules\Api\Http\Requests\Mobile\Concerns\ValidatesDeviceId;
use App\Modules\Identity\Http\Requests\Storefront\RegisterCustomerRequest;

/**
 * Mobile sign-up: the storefront registration rules (intl phone: number + ISO2 country)
 * plus the app install id.
 */
class MobileRegisterRequest extends RegisterCustomerRequest
{
    use ValidatesDeviceId;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['remember'], $rules['redirect']);

        return [...$rules, ...$this->deviceIdRules()];
    }
}
