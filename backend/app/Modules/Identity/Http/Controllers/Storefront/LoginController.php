<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\DTOs\LoginCredentialsData;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Services\CustomerAuthService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\SafeRedirect;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Storefront customer sign-in ({store}/account/login) and sign-out. `redirect` is
 * honoured only for same-host targets (SafeRedirect).
 */
class LoginController extends Controller
{
    public function __construct(private readonly CustomerAuthService $auth) {}

    public function create(Request $request): View
    {
        return view('identity::storefront.login', [
            'redirect' => SafeRedirect::target($request->query('redirect'), $request),
            'socialError' => $request->session()->get('social_error'),
        ]);
    }

    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $this->auth->login(LoginCredentialsData::fromRequest($request), $request);
        $target = $this->target($request);

        if ($request->expectsJson()) {
            return JsonResponder::success(__('identity::account.signed_in', ['name' => $customer->name]), redirect: $target);
        }

        return redirect()->to($target);
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $this->auth->logout($request);

        if ($request->expectsJson()) {
            return JsonResponder::success(__('identity::account.signed_out'), redirect: route('storefront.home'));
        }

        return redirect()->route('storefront.home');
    }

    private function target(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        return SafeRedirect::target($request->input('redirect'), $request)
            ?? SafeRedirect::target($intended, $request)
            ?? route('storefront.home');
    }
}
