<?php

namespace App\Modules\Api\Http\Requests\Mobile\Concerns;

/**
 * Optional app install id sent with sign-in / sign-up / refresh (names the token pair and
 * binds the push device to the customer).
 */
trait ValidatesDeviceId
{
    /**
     * @return array<string, list<string>>
     */
    protected function deviceIdRules(): array
    {
        return ['device_id' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']];
    }

    public function deviceId(): ?string
    {
        $value = $this->validated('device_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
