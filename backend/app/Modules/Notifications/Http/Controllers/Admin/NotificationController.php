<?php

namespace App\Modules\Notifications\Http\Controllers\Admin;

use App\Modules\Identity\Models\Admin;
use App\Modules\Notifications\DataTables\PanelNotificationsDataTable;
use App\Modules\Notifications\DTOs\NotificationPreferencesData;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Http\Requests\Admin\MarkNotificationRequest;
use App\Modules\Notifications\Http\Requests\Admin\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Services\NotificationInboxService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin › Notifications (ADM-007 inbox / ADM-007a preferences): category chips, open,
 * mark read / unread, mark all read, delete, per-channel switches, the bell feed and
 * the Reverb channel auth. Every action is scoped to the signed-in admin's own rows.
 */
class NotificationController extends Controller
{
    private const SURFACE = 'admin';

    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function index(Request $request): View
    {
        $admin = $this->admin($request);

        return view('notifications::admin.index', [
            'tab' => 'inbox',
            'unread' => $this->inbox->unreadCount($admin),
            'unreadByCategory' => $this->inbox->unreadByCategory($admin),
            'categories' => NotificationCategory::cases(),
            'channels' => NotificationChannel::cases(),
            'matrix' => $this->preferences->matrix($admin),
        ]);
    }

    public function preferences(Request $request): View
    {
        $admin = $this->admin($request);

        return view('notifications::admin.preferences', [
            'tab' => 'preferences',
            'unread' => $this->inbox->unreadCount($admin),
            'categories' => NotificationCategory::cases(),
            'channels' => NotificationChannel::cases(),
            'matrix' => $this->preferences->matrix($admin),
        ]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $matrix = $this->preferences->update($this->admin($request), new NotificationPreferencesData($request->preferences()));

        return JsonResponder::success(__('notifications::panel.preferences.saved'), ['preferences' => $matrix]);
    }

    public function data(Request $request, PanelNotificationsDataTable $table): JsonResponse
    {
        return $table->for($this->admin($request), self::SURFACE)->toJsonFromRequest($request, PanelNotificationsDataTable::FILTERS);
    }

    public function latest(Request $request): JsonResponse
    {
        $admin = $this->admin($request);

        return JsonResponder::success(data: [
            'items' => $this->inbox->bellItems($admin, self::SURFACE),
            'unread' => $this->inbox->unreadCount($admin),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->inbox->findOwned($this->admin($request), $notification);

        return redirect()->to($this->inbox->open($record, self::SURFACE));
    }

    public function mark(MarkNotificationRequest $request, string $notification): JsonResponse
    {
        $admin = $this->admin($request);
        $record = $this->inbox->findOwned($admin, $notification);
        $request->read() ? $this->inbox->markRead($record) : $this->inbox->markUnread($record);

        return JsonResponder::success(
            __($request->read() ? 'notifications::panel.inbox.marked_read' : 'notifications::panel.inbox.marked_unread'),
            ['unread' => $this->inbox->unreadCount($admin)],
        );
    }

    public function markAll(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $count = $this->inbox->markAllRead($admin);

        return JsonResponder::success(trans_choice('notifications::panel.inbox.marked_all', $count, ['count' => $count]), ['unread' => 0]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $admin = $this->admin($request);
        $this->inbox->delete($this->inbox->findOwned($admin, $notification));

        return JsonResponder::success(__('notifications::panel.inbox.deleted'), ['unread' => $this->inbox->unreadCount($admin)]);
    }

    /**
     * Pusher-protocol channel auth for Reverb (private-admin.{id}).
     */
    public function broadcastAuth(Request $request): Response
    {
        $request->setUserResolver(fn () => $this->admin($request));

        $result = Broadcast::auth($request);

        return $result instanceof Response ? $result : response()->json($result);
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }
}
