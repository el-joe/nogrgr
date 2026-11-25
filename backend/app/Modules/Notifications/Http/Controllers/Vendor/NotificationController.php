<?php

namespace App\Modules\Notifications\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Services\VendorNotificationInboxService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vendor topbar bell (VEN-006): latest rows, open (mark read + go), mark all read and
 * the Reverb channel auth of `tenant.{tenant}.vendor.{id}`.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly VendorNotificationInboxService $inbox) {}

    public function latest(Request $request): JsonResponse
    {
        $staff = $this->staff($request);

        return JsonResponder::success(data: [
            'items' => $this->inbox->bellItems($staff),
            'unread' => $this->inbox->unreadCount($staff),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        return redirect()->to($this->inbox->open($this->inbox->findOwned($this->staff($request), $notification)));
    }

    public function markAll(Request $request): JsonResponse
    {
        $count = $this->inbox->markAllRead($this->staff($request));

        return JsonResponder::success(trans_choice('notifications::panel.inbox.marked_all', $count, ['count' => $count]), ['unread' => 0]);
    }

    public function broadcastAuth(Request $request): Response
    {
        $request->setUserResolver(fn () => $this->staff($request));
        $result = Broadcast::auth($request);

        return $result instanceof Response ? $result : response()->json($result);
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
