<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\DTOs\LoginCredentialsData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Services\SessionAuthService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sign-in / sign-out of a central panel (admin, affiliate) on {central}/{surface}.
 */
abstract class PanelLoginController extends Controller
{
    public function __construct(protected readonly SessionAuthService $auth) {}

    abstract protected function guard(): Guard;

    /**
     * Extra data for the sign-in page (e.g. live platform counts).
     *
     * @return array<string, mixed>
     */
    protected function pageData(): array
    {
        return [];
    }

    public function create(): View
    {
        $surface = $this->guard()->value;

        return view('identity::auth.login', [
            'surface' => $surface,
            'action' => route($surface.'.login.store'),
            ...$this->pageData(),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->auth->attempt($this->guard(), LoginCredentialsData::fromRequest($request), $request);

        return redirect()->intended(route($this->guard()->value.'.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout($this->guard(), $request);

        return redirect()->route($this->guard()->value.'.login');
    }
}
