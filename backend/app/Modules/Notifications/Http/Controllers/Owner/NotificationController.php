<?php

namespace App\Modules\Notifications\Http\Controllers\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Notifications\DataTables\PanelNotificationsDataTable;
use App\Modules\Notifications\Http\Requests\Admin\MarkNotificationRequest;
use App\Modules\Notifications\Services\NotificationInboxService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner portal › Notifications (OWN-008, Figma Affiliate & Owner 122:105109): All /
 * Unread tabs, open (mark read + follow), mark read / unread, mark all as read, the
 * topbar bell feed and the Reverb auth of "owner.{id}". Own rows only.
 */
class NotificationController extends Controller
{
    private const SURFACE = 'owner';

    public function __construct(private readonly NotificationInboxService $inbox) {}

    public function index(Request $request): View
    {
        $owner = $this->owner($request);

        return view('notifications::owner.index', [
            'unread' => $this->inbox->unreadCount($owner),
            'total' => $this->inbox->totalCount($owner),
            'state' => $request->query('state') === 'unread' ? 'unread' : '',
        ]);
    }

    public function data(Request $request, PanelNotificationsDataTable $table): JsonResponse
    {
        return $table->for($this->owner($request), self::SURFACE)->withCellView('notifications::owner._row')->toJsonFromRequest($request, PanelNotificationsDataTable::FILTERS);
    }

    public function latest(Request $request): JsonResponse
    {
        $owner = $this->owner($request);

        return JsonResponder::success(data: [
            'items' => $this->inbox->bellItems($owner, self::SURFACE),
            'unread' => $this->inbox->unreadCount($owner),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        return redirect()->to($this->inbox->open($this->inbox->findOwned($this->owner($request), $notification), self::SURFACE));
    }

    public function mark(MarkNotificationRequest $request, string $notification): JsonResponse
    {
        $owner = $this->owner($request);
        $record = $this->inbox->findOwned($owner, $notification);
        $request->read() ? $this->inbox->markRead($record) : $this->inbox->markUnread($record);

        return JsonResponder::success(
            __($request->read() ? 'notifications::panel.inbox.marked_read' : 'notifications::panel.inbox.marked_unread'),
            ['unread' => $this->inbox->unreadCount($owner)],
        );
    }

    public function markAll(Request $request): JsonResponse
    {
        $count = $this->inbox->markAllRead($this->owner($request));

        return JsonResponder::success(trans_choice('notifications::panel.inbox.marked_all', $count, ['count' => $count]), ['unread' => 0]);
    }

    public function broadcastAuth(Request $request): Response
    {
        $request->setUserResolver(fn () => $this->owner($request));
        $result = Broadcast::auth($request);

        return $result instanceof Response ? $result : response()->json($result);
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
