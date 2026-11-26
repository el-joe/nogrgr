<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Storefront\Http\Requests\Storefront\UpdatePreferencesRequest;
use App\Modules\Storefront\Services\StorefrontPreferencesService;
use App\Shared\Http\Controller;
use App\Shared\Http\SafeRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * POST {store}/preferences (storefront.preferences): locale switcher submit. Redirects
 * back (same host only) with `?lang=` when the language changed; JSON for fetch().
 */
class PreferencesController extends Controller
{
    use ResolvesStoreApiContext;

    public function __invoke(UpdatePreferencesRequest $request, StorefrontPreferencesService $preferences): RedirectResponse|JsonResponse
    {
        $data = $request->toData();
        $preferences->apply($data, $this->cartContext($request));

        $target = SafeRedirect::target($request->validated('redirect'), $request) ?? route('storefront.home');

        if ($data->locale !== null) {
            $target .= (str_contains($target, '?') ? '&' : '?').'lang='.$data->locale;
        }

        return $request->expectsJson()
            ? response()->json(['success' => true, 'data' => ['redirect' => $target]])
            : redirect()->to($target);
    }
}
