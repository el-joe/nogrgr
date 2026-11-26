<?php

namespace App\Modules\Ai\Events;

use App\Modules\Localization\Models\StoreTranslationRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live progress of a paid store translation run over Reverb: private channel
 * "tenant.<id>.translation-runs" (store staff with settings.languages.view), read by
 * Vendor › Settings › Languages and AI translation.
 */
final class StoreTranslationRunUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly StoreTranslationRun $run) {}

    public static function channelName(int $tenantId): string
    {
        return 'tenant.'.$tenantId.'.translation-runs';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(self::channelName($this->run->tenant_id));
    }

    public function broadcastAs(): string
    {
        return 'translation-run.updated';
    }

    /**
     * @return array{id: int, code: string, status: string, progress: int, done: int, total: int, failed: int, error: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->run->id,
            'code' => $this->run->language_code,
            'status' => $this->run->status->value,
            'progress' => $this->run->progress,
            'done' => $this->run->done_items,
            'total' => $this->run->total_items,
            'failed' => $this->run->failed_items,
            'error' => $this->run->last_error,
        ];
    }
}
