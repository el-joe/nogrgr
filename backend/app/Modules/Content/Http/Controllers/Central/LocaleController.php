<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Shared\Http\Controller;
use App\Shared\Http\Middleware\SetLocale;
use App\Shared\Http\SafeRedirect;
use App\Shared\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Language switcher (WEB-025): remembers the locale (SetLocale cookie) and returns to
 * the page the visitor came from on this host.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::isSupported($locale), 404);

        $target = SafeRedirect::target($request->query('to') ?? url()->previous(), $request) ?? route('central.home');
        $target = preg_replace('/([?&])lang=[^&]*(&|$)/', '$1', $target) ?? $target;
        $target = rtrim($target, '?&');

        return redirect()->to($target)->withCookie(Cookie::make(SetLocale::COOKIE, $locale, 60 * 24 * 365, httpOnly: false));
    }
}
