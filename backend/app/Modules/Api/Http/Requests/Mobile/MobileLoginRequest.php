<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use App\Modules\Api\Http\Requests\Mobile\Concerns\ValidatesDeviceId;
use App\Modules\Identity\Http\Requests\LoginRequest;

/**
 * Mobile sign-in: e-mail + password (+ app install id).
 */
class MobileLoginRequest extends LoginRequest
{
    use ValidatesDeviceId;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:191'],
            ...$this->deviceIdRules(),
        ];
    }
}
