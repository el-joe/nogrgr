<?php

namespace App\Modules\Ai\Events;

use App\Modules\Ai\Models\AdminTranslationRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live progress of an admin AI translation run over Reverb: private channel
 * "admin.translation-runs" (admins who can view languages or translations), read by
 * Admin › Languages and the translations editor (they re-read the run's status URL).
 */
final class AdminTranslationRunUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public const CHANNEL = 'admin.translation-runs';

    public function __construct(public readonly AdminTranslationRun $run) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(self::CHANNEL);
    }

    public function broadcastAs(): string
    {
        return 'admin-translation-run.updated';
    }

    /**
     * @return array{id: int, kind: string, locale: string, status: string, progress: int}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->run->id,
            'kind' => $this->run->kind->value,
            'locale' => $this->run->locale,
            'status' => $this->run->status->value,
            'progress' => $this->run->progress,
        ];
    }
}
