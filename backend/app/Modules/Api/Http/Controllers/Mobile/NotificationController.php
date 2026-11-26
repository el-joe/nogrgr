<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Concerns\ResolvesMobileCustomer;
use App\Modules\Api\Http\Requests\Mobile\NotificationIndexRequest;
use App\Modules\Api\Http\Resources\Mobile\NotificationResource;
use App\Modules\Api\Services\CustomerInboxService;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications inbox of the app: list (unread filter), unread count, read one, read
 * all, delete one. Another customer's id answers 404.
 */
class NotificationController extends Controller
{
    use ResolvesMobileCustomer;

    public function __construct(private readonly CustomerInboxService $inbox) {}

    public function index(NotificationIndexRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $page = $this->inbox->page($customer, $request->boolean('unread'), $request->perPage(), $request->page());

        return JsonResponder::success('', [
            'items' => collect($page->items())->map(static fn (PanelNotificationRecord $record): array => (new NotificationResource($record))->resolve($request))->all(),
            'unread_count' => $this->inbox->unreadCount($customer),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return JsonResponder::success('', ['unread_count' => $this->inbox->unreadCount($this->customer($request))]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $customer = $this->customer($request);
        $record = $this->inbox->markRead($customer, $notification);
        abort_if($record === null, 404);

        return JsonResponder::success('', [
            'notification' => (new NotificationResource($record))->resolve($request),
            'unread_count' => $this->inbox->unreadCount($customer),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $this->inbox->markAllRead($customer);

        return JsonResponder::success((string) __('api::mobile.notifications_read'), ['unread_count' => 0]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($this->inbox->delete($customer, $notification), 404);

        return JsonResponder::success((string) __('api::mobile.notification_deleted'), ['unread_count' => $this->inbox->unreadCount($customer)]);
    }
}
