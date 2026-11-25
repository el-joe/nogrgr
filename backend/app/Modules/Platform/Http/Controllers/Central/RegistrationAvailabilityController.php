<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Http\Requests\Central\RegistrationAvailabilityRequest;
use App\Modules\Platform\Http\Requests\Central\StoreAddressCheck;
use App\Modules\Platform\Services\VendorRegistrationService;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Live availability for the store step (debounced from register.js):
 * {available, message} for ?slug= or ?domain=, plus `records` when check=dns.
 */
class RegistrationAvailabilityController extends Controller
{
    public function __construct(private readonly VendorRegistrationService $registrations) {}

    public function __invoke(RegistrationAvailabilityRequest $request): JsonResponse
    {
        $registration = $this->registrations->resolveToken((string) $request->validated('token'));

        if ($registration === null || $registration->status === RegistrationStatus::Completed || $registration->isExpired()) {
            return response()->json(['available' => false, 'message' => __('platform::messages.registration_closed')], 403);
        }

        $check = new StoreAddressCheck($this->registrations);
        $domain = $request->validated('domain');

        if (is_string($domain) && $domain !== '') {
            $error = $check->domainError($domain);
            $payload = [
                'available' => $error === null,
                'message' => $error ?? __('platform::register.store.domain_available', ['host' => $domain]),
            ];

            $slug = StoreAddressCheck::normalizeSlug($request->validated('for'));

            if ($error === null && $request->validated('check') === 'dns') {
                $slug = is_string($slug) && TenantSlug::isValid($slug) ? $slug : 'your-store';
                $payload['records'] = $this->registrations->dnsPreview($domain, $slug);
                $payload['subdomain'] = TenantSlug::subdomainHost($slug);
            }

            return response()->json($payload);
        }

        $slug = (string) $request->validated('slug');
        $error = $check->slugError($slug, $registration->id);

        return response()->json([
            'available' => $error === null,
            'message' => $error ?? __('platform::register.store.available', ['host' => TenantSlug::subdomainHost($slug)]),
        ]);
    }
}
