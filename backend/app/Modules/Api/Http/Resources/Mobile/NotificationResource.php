<?php

namespace App\Modules\Api\Http\Resources\Mobile;

use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Services\CustomerPushService;
use App\Modules\Notifications\Support\InboxText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer inbox row, translated into the request language.
 *
 * @mixin PanelNotificationRecord
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];
        $event = (string) ($data['event'] ?? '');

        return [
            'id' => $this->id,
            'event' => $event !== '' ? $event : null,
            'type' => $event !== '' ? CustomerPushService::typeOf($event)->value : null,
            'title' => self::text($data, 'title'),
            'body' => self::text($data, 'body'),
            'order_uuid' => is_string($data['order_uuid'] ?? null) ? $data['order_uuid'] : null,
            'ticket_id' => is_int($data['ticket_id'] ?? null) ? $data['ticket_id'] : null,
            'store_product_id' => is_int($data['store_product_id'] ?? null) ? $data['store_product_id'] : null,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $field): string
    {
        return InboxText::render($data, $field);
    }
}
