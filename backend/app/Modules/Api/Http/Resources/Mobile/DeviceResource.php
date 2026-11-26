<?php

namespace App\Modules\Api\Http\Resources\Mobile;

use App\Modules\Notifications\Models\CustomerDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An app install (the FCM token itself is never echoed back).
 *
 * @mixin CustomerDevice
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->device_id,
            'platform' => $this->platform->value,
            'has_push_token' => $this->push_token !== null,
            'push_enabled' => $this->push_enabled,
            'app_version' => $this->app_version,
            'signed_in' => $this->customer_id !== null,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
