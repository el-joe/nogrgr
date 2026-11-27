<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Models\SentReminder;
use App\Modules\Notifications\Repositories\Contracts\SentReminderRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<SentReminder>
 */
class SentReminderRepository extends BaseRepository implements SentReminderRepositoryContract
{
    protected function model(): string
    {
        return SentReminder::class;
    }

    public function claim(string $reminder, string $subject, string $period): bool
    {
        return $this->query()->insertOrIgnore([
            'reminder' => $reminder,
            'subject' => $subject,
            'period' => $period,
            'sent_at' => now(),
        ]) === 1;
    }

    public function release(string $reminder, string $subject, string $period): void
    {
        $this->query()->where(['reminder' => $reminder, 'subject' => $subject, 'period' => $period])->delete();
    }
}
