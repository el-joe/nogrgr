<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<EmailTemplate>
 */
interface EmailTemplateRepositoryContract extends RepositoryContract
{
    public function findByEvent(EmailTemplateEvent $event): ?EmailTemplate;

    /**
     * @return list<string> event values that already have a template
     */
    public function existingEvents(): array;

    /**
     * Count per audience value + "all".
     *
     * @return array<string, int>
     */
    public function counts(): array;

    /**
     * @param  list<int>  $ids  empty = every template
     * @return Collection<int, EmailTemplate>
     */
    public function forSync(array $ids): Collection;

    /**
     * @param  list<int>  $ids
     */
    public function markSynced(array $ids): void;
}
