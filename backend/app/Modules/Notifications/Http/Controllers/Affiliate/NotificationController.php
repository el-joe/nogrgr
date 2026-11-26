<?php

namespace App\Modules\Notifications\Http\Controllers\Affiliate;

use App\Modules\Identity\Models\Affiliate;
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
 * Affiliate › Notifications (AFF-014): inbox with read-state filter, open, mark read /
 * unread, mark all read, delete, the bell feed and the Reverb channel auth
 * (private-affiliate.{id}). Every action is scoped to the signed-in affiliate's rows.
 */
class NotificationController extends Controller
{
    private const SURFACE = 'affiliate';

    public function __construct(private readonly NotificationInboxService $inbox) {}

    public function index(Request $request): View
    {
        $affiliate = $this->affiliate($request);

        return view('notifications::affiliate.index', [
            'unread' => $this->inbox->unreadCount($affiliate),
        ]);
    }

    public function data(Request $request, PanelNotificationsDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate($request), self::SURFACE)->toJsonFromRequest($request, PanelNotificationsDataTable::FILTERS);
    }

    public function latest(Request $request): JsonResponse
    {
        $affiliate = $this->affiliate($request);

        return JsonResponder::success(data: [
            'items' => $this->inbox->bellItems($affiliate, self::SURFACE),
            'unread' => $this->inbox->unreadCount($affiliate),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->inbox->findOwned($this->affiliate($request), $notification);

        return redirect()->to($this->inbox->open($record, self::SURFACE));
    }

    public function mark(MarkNotificationRequest $request, string $notification): JsonResponse
    {
        $affiliate = $this->affiliate($request);
        $record = $this->inbox->findOwned($affiliate, $notification);
        $request->read() ? $this->inbox->markRead($record) : $this->inbox->markUnread($record);

        return JsonResponder::success(
            __($request->read() ? 'notifications::panel.inbox.marked_read' : 'notifications::panel.inbox.marked_unread'),
            ['unread' => $this->inbox->unreadCount($affiliate)],
        );
    }

    public function markAll(Request $request): JsonResponse
    {
        $affiliate = $this->affiliate($request);
        $count = $this->inbox->markAllRead($affiliate);

        return JsonResponder::success(trans_choice('notifications::panel.inbox.marked_all', $count, ['count' => $count]), ['unread' => 0]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $affiliate = $this->affiliate($request);
        $this->inbox->delete($this->inbox->findOwned($affiliate, $notification));

        return JsonResponder::success(__('notifications::panel.inbox.deleted'), ['unread' => $this->inbox->unreadCount($affiliate)]);
    }

    /**
     * Pusher-protocol channel auth for Reverb (private-affiliate.{id}).
     */
    public function broadcastAuth(Request $request): Response
    {
        $request->setUserResolver(fn () => $this->affiliate($request));

        $result = Broadcast::auth($request);

        return $result instanceof Response ? $result : response()->json($result);
    }

    private function affiliate(Request $request): Affiliate
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        return $affiliate;
    }
}
