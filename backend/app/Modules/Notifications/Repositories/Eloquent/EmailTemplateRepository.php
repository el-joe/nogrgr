<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<EmailTemplate>
 */
class EmailTemplateRepository extends BaseRepository implements EmailTemplateRepositoryContract
{
    protected function model(): string
    {
        return EmailTemplate::class;
    }

    public function findByEvent(EmailTemplateEvent $event): ?EmailTemplate
    {
        return $this->query()->where('event', $event->value)->first();
    }

    public function existingEvents(): array
    {
        return $this->query()->pluck('event')
            ->map(static fn (mixed $event): string => $event instanceof EmailTemplateEvent ? $event->value : (string) $event)
            ->values()->all();
    }

    public function counts(): array
    {
        $rows = $this->query()->selectRaw('audience, count(*) as aggregate')->groupBy('audience')->pluck('aggregate', 'audience');
        $counts = ['all' => (int) $rows->sum()];

        foreach (EmailAudience::cases() as $audience) {
            $counts[$audience->value] = (int) ($rows[$audience->value] ?? 0);
        }

        return $counts;
    }

    public function forSync(array $ids): Collection
    {
        return $this->query()->when($ids !== [], static fn ($query) => $query->whereKey($ids))->orderBy('id')->get();
    }

    public function markSynced(array $ids): void
    {
        $this->query()->when($ids !== [], static fn ($query) => $query->whereKey($ids))->update(['synced_at' => Carbon::now()]);
    }
}
