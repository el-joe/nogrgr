<?php

namespace App\Modules\Engagement\Http\Controllers\Vendor;

use App\Modules\Engagement\DataTables\VendorSubscribersDataTable;
use App\Modules\Engagement\Enums\SubscriberSource;
use App\Modules\Engagement\Enums\SubscriberStatus;
use App\Modules\Engagement\Http\Requests\Vendor\SubscriberStatusRequest;
use App\Modules\Engagement\Services\VendorSubscriberService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Newsletter subscribers (VEN-026, Figma 84:106555): the store's newsletter list
 * with e-mail search, language / status / source filters, CSV export, unsubscribe /
 * re-subscribe and delete. Tenant context (store_newsletter_subscribers).
 */
class SubscriberController extends Controller
{
    public function __construct(private readonly VendorSubscriberService $subscribers) {}

    public function index(): View
    {
        return view('engagement::vendor.subscribers.index', [
            'locales' => $this->subscribers->localeOptions(),
            'statuses' => SubscriberStatus::options(),
            'sources' => SubscriberSource::options(),
        ]);
    }

    public function data(Request $request, VendorSubscribersDataTable $table): JsonResponse
    {
        return $table
            ->withAbilities($this->can($request, 'subscribers.manage'), $this->can($request, 'customers.view'))
            ->toJsonFromRequest($request, VendorSubscribersDataTable::FILTERS);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->subscribers->export($request->only(VendorSubscribersDataTable::FILTERS));
    }

    public function status(SubscriberStatusRequest $request, int $subscriber): JsonResponse
    {
        $row = $this->subscribers->setSubscribed($this->subscribers->find($subscriber), $request->subscribed());

        return JsonResponder::success(__($request->subscribed() ? 'engagement::subscribers.resubscribed' : 'engagement::subscribers.unsubscribed', ['email' => $row->email]));
    }

    public function destroy(int $subscriber): JsonResponse
    {
        $row = $this->subscribers->find($subscriber);
        $this->subscribers->delete($row);

        return JsonResponder::success(__('engagement::subscribers.deleted', ['email' => $row->email]));
    }

    private function can(Request $request, string $permission): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can($permission);
    }
}
