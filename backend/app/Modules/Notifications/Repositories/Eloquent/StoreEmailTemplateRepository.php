<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\StoreEmailTemplateRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreEmailTemplate>
 */
class StoreEmailTemplateRepository extends BaseRepository implements StoreEmailTemplateRepositoryContract
{
    protected function model(): string
    {
        return StoreEmailTemplate::class;
    }

    public function findByEvent(EmailTemplateEvent $event): ?StoreEmailTemplate
    {
        return $this->query()->where('event', $event->value)->first();
    }

    public function allByEvent(): Collection
    {
        return $this->query()->get()->keyBy(static fn (StoreEmailTemplate $template): string => $template->event->value);
    }
}
