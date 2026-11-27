<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Models\SentReminder;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Scheduled reminder idempotency log (central).
 *
 * @extends RepositoryContract<SentReminder>
 */
interface SentReminderRepositoryContract extends RepositoryContract
{
    /**
     * Records the reminder; false when it was already recorded (send nothing).
     */
    public function claim(string $reminder, string $subject, string $period): bool;

    public function release(string $reminder, string $subject, string $period): void;
}
