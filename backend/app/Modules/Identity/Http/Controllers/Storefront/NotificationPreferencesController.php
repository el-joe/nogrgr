<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Http\Requests\Storefront\UpdateNotificationPreferencesRequest;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerNotificationPreferences;
use App\Shared\Http\Concerns\RespondsWithJsonOrRedirect;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Account › Notifications ({store}/account/notifications): email / push toggles per type.
 */
class NotificationPreferencesController extends Controller
{
    use RespondsWithJsonOrRedirect;

    public function __construct(private readonly CustomerNotificationPreferences $preferences) {}

    public function edit(Request $request): View
    {
        return view('identity::storefront.account.notifications', [
            'preferences' => $this->preferences->for($this->customer($request)),
            'types' => CustomerNotificationType::cases(),
            'channels' => CustomerNotificationChannel::cases(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse|RedirectResponse
    {
        $matrix = $this->preferences->update($this->customer($request), $request->preferences());

        return $this->respond($request, __('identity::account.notifications.saved'), ['preferences' => $matrix]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
