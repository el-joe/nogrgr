<?php

namespace App\Modules\Engagement\Http\Controllers\Storefront;

use App\Modules\Engagement\Exceptions\EngagementException;
use App\Modules\Engagement\Http\Requests\Storefront\ToggleFavoriteRequest;
use App\Modules\Engagement\Services\FavoritesService;
use App\Modules\Identity\Models\Customer;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Storefront favorites JSON (SF-ECO-033): toggle the heart (guests get 401 + the login
 * URL) and the favorited ids of the signed-in customer (guests: none).
 */
class FavoriteController extends Controller
{
    public function __construct(private readonly FavoritesService $favorites) {}

    public function toggle(ToggleFavoriteRequest $request): JsonResponse
    {
        $customer = $this->customer();

        if ($customer === null) {
            throw EngagementException::signInRequired(route('storefront.account.login'));
        }

        $result = $this->favorites->toggle($customer, $request->productId());

        return JsonResponder::success(__st($result['favorited'] ? 'favorites.added' : 'favorites.removed'), $result);
    }

    public function ids(Request $request): JsonResponse
    {
        $customer = $this->customer();

        return JsonResponder::success('', ['ids' => $this->favorites->idsFor($customer), 'count' => $this->favorites->count($customer)]);
    }

    private function customer(): ?Customer
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer ? $customer : null;
    }
}
