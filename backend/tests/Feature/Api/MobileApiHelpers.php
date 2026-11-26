<?php

use App\Modules\Platform\Models\Tenant;

/*
| Shared helpers of the Mobile API v1 tests (/api/mobile/v1, X-Tenant header).
*/

if (! function_exists('mobileApi')) {
    function mobileApi(string $path): string
    {
        return '/api/mobile/v1/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    function mobileHeaders(Tenant $tenant, ?string $token = null, array $extra = []): array
    {
        return array_filter(['X-Tenant' => (string) $tenant->id, 'Accept' => 'application/json', 'Authorization' => $token !== null ? 'Bearer '.$token : null] + $extra);
    }

    /**
     * Signs the customer in through the API and returns the token pair.
     *
     * @return array{access_token: string, refresh_token: string}
     */
    function mobileLogin(Tenant $tenant, string $email, string $password = 'password', ?string $deviceId = null): array
    {
        return test()->postJson(mobileApi('auth/login'), array_filter(['email' => $email, 'password' => $password, 'device_id' => $deviceId]), mobileHeaders($tenant))
            ->assertOk()
            ->json('data.tokens');
    }
}
