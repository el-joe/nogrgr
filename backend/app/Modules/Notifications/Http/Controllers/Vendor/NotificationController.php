<?php

namespace App\Modules\Notifications\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\DataTables\VendorNotificationsDataTable;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Http\Requests\Vendor\MarkNotificationRequest;
use App\Modules\Notifications\Services\VendorNotificationInboxService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vendor notifications: the inbox page (VEN-090, Figma 12:17282: feed, mark read /
 * unread, mark all read), the topbar bell (VEN-006: latest rows, open = mark read + go)
 * and the Reverb channel auth of `tenant.{tenant}.vendor.{id}`. Own rows only.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly VendorNotificationInboxService $inbox) {}

    public function index(Request $request): View
    {
        $staff = $this->staff($request);

        return view('notifications::vendor.index', [
            'unread' => $this->inbox->unreadCount($staff),
            'categories' => NotificationCategory::cases(),
        ]);
    }

    public function data(Request $request, VendorNotificationsDataTable $table): JsonResponse
    {
        return $table->for($this->staff($request))->toJsonFromRequest($request, VendorNotificationsDataTable::FILTERS);
    }

    public function mark(MarkNotificationRequest $request, string $notification): JsonResponse
    {
        $staff = $this->staff($request);
        $record = $this->inbox->findOwned($staff, $notification);
        $request->read() ? $this->inbox->markRead($record) : $this->inbox->markUnread($record);

        return JsonResponder::success(
            __($request->read() ? 'notifications::panel.inbox.marked_read' : 'notifications::panel.inbox.marked_unread'),
            ['unread' => $this->inbox->unreadCount($staff)],
        );
    }

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
