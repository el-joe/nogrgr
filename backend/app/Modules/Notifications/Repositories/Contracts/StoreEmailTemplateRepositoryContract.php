<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * The store's e-mail templates (tenant DB, VEN-080).
 *
 * @extends RepositoryContract<StoreEmailTemplate>
 */
interface StoreEmailTemplateRepositoryContract extends RepositoryContract
{
    public function findByEvent(EmailTemplateEvent $event): ?StoreEmailTemplate;

    /**
     * @return Collection<string, StoreEmailTemplate> keyed by event value
     */
    public function allByEvent(): Collection;
}
