<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\DTOs\AdminProfileData;
use App\Modules\Identity\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Services\AdminProfileService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\Middleware\SetLocale;
use App\Shared\Support\Locale;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Admin › My profile (ADM-005, Figma Admin Panel 80:97736): avatar, name, email,
 * language, timezone, and password change with the current password.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AdminProfileService $profiles) {}

    public function edit(Request $request): View
    {
        $timezones = [];

        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $timezones[$timezone] = str_replace('_', ' ', $timezone);
        }

        return view('identity::admin.account.edit', [
            'admin' => $this->admin($request),
            'languages' => collect(Locale::enabled())->map(fn (array $language): string => $language['native'])->all(),
            'timezones' => $timezones,
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): JsonResponse
    {
        $admin = $this->admin($request);
        $previousLocale = $admin->getAttributes()['locale'] ?? null;
        $admin = $this->profiles->update($admin, AdminProfileData::fromArray($request->payload()));

        if ($admin->locale !== null && $admin->locale !== $previousLocale) {
            Cookie::queue(Cookie::make(SetLocale::COOKIE, $admin->locale, 60 * 24 * 365, httpOnly: false));

            return JsonResponder::success(__('identity::profile.updated', [], $admin->locale), redirect: route('admin.account.edit'));
        }

        return JsonResponder::success(__('identity::profile.updated'), ['avatar_url' => $admin->avatar_url]);
    }

    public function password(UpdateAdminPasswordRequest $request): JsonResponse
    {
        $admin = $this->admin($request);
        $this->profiles->changePassword($admin, (string) $request->validated('password'));

        $request->session()->regenerate();

        return JsonResponder::success(__('identity::profile.password_changed'));
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }
}
