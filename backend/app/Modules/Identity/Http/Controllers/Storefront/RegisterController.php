<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\DTOs\RegisterCustomerData;
use App\Modules\Identity\Http\Requests\Storefront\RegisterCustomerRequest;
use App\Modules\Identity\Services\CustomerAuthService;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\SafeRedirect;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Storefront customer registration ({store}/account/register). Signs the new customer in.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly CustomerAuthService $auth, private readonly StorefrontContext $context) {}

    public function create(Request $request): View
    {
        return view('identity::storefront.register', [
            'redirect' => SafeRedirect::target($request->query('redirect'), $request),
            'phoneCountry' => $this->context->phoneCountry(),
        ]);
    }

    public function store(RegisterCustomerRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $this->auth->register(RegisterCustomerData::fromRequest($request), $request);
        $target = SafeRedirect::target($request->input('redirect'), $request) ?? route('storefront.account.profile');

        if ($request->expectsJson()) {
            return JsonResponder::created(__('identity::account.registered', ['name' => $customer->name]), redirect: $target);
        }

        return redirect()->to($target)->with('status', __('identity::account.registered', ['name' => $customer->name]));
    }
}
