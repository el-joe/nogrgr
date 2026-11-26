<?php

namespace App\Modules\Ai\Events;

use App\Modules\Ai\Models\VideoAd;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live progress of an AI Advertising Studio ad over Reverb: private channel
 * "tenant.<id>.video-ads" (store staff with marketing.ads.view). The studio modal, the
 * Advertising Library and the dashboard widget re-read the ad's status URL on each push.
 */
final class VideoAdUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly int $tenantId, public readonly VideoAd $ad) {}

    public static function channelName(int $tenantId): string
    {
        return 'tenant.'.$tenantId.'.video-ads';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(self::channelName($this->tenantId));
    }

    public function broadcastAs(): string
    {
        return 'video-ad.updated';
    }

    /**
     * @return array{id: int, status: string, progress: int}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->ad->id, 'status' => $this->ad->status->value, 'progress' => $this->ad->progress];
    }
}
