<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\Http\Requests\Vendor\UpdateVendorAccountRequest;
use App\Modules\Identity\Http\Requests\Vendor\UpdateVendorPasswordRequest;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Services\VendorAccountService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\Middleware\SetLocale;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Vendor › Settings › Account (VEN-070, Figma Vendor Panel 90:111315): the signed-in
 * member's profile (name, e-mail, phone, language), password, and — for the store owner —
 * the Google / Apple sign-in linked to the owner account. Every member may edit only
 * themself (route permission dashboard.view, like the sidebar entry).
 */
class AccountController extends Controller
{
    public function __construct(private readonly VendorAccountService $accounts) {}

    public function edit(Request $request): View
    {
        $staff = $this->staff($request);

        return view('identity::vendor.account.edit', [
            'staff' => $staff,
            'isOwner' => $this->accounts->isStoreOwner($staff),
            'languages' => collect(Locale::enabled())->map(fn (array $language): string => $language['native'])->all(),
            'linked' => $this->accounts->linkedSignIn($staff),
        ]);
    }

    public function update(UpdateVendorAccountRequest $request): JsonResponse
    {
        $staff = $this->staff($request);
        $previousLocale = $staff->locale;
        $previousEmail = $staff->email;
        $staff = $this->accounts->update($staff, $request->toData());

        $locale = $staff->locale ?? app()->getLocale();
        $message = $staff->email !== $previousEmail
            ? __('identity::vendor_account.updated_verify', ['email' => $staff->email], $locale)
            : __('identity::vendor_account.updated', [], $locale);

        if ($staff->locale !== null && $staff->locale !== $previousLocale) {
            Cookie::queue(Cookie::make(SetLocale::COOKIE, $staff->locale, 60 * 24 * 365, httpOnly: false));

            return JsonResponder::success($message, redirect: vendor_route('settings.account'));
        }

        return JsonResponder::success($message);
    }

    public function password(UpdateVendorPasswordRequest $request): JsonResponse
    {
        $this->accounts->changePassword($this->staff($request), (string) $request->validated('password'));
        $request->session()->regenerate();

        return JsonResponder::success(__('identity::vendor_account.password_changed'));
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        // A fresh copy: the guard's instance may not carry every column (strict models).
        return $staff->fresh() ?? $staff;
    }
}
