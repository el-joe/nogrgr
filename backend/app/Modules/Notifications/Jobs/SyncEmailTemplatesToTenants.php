<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Events\EmailTemplatesSynced;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Publishes central templates (all, or the given ids) to tenants: snapshots them,
 * fires EmailTemplatesSynced for the tenant-side consumers and stamps `synced_at`.
 */
class SyncEmailTemplatesToTenants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $templateIds  empty = every template
     */
    public function __construct(public readonly array $templateIds = []) {}

    public function handle(EmailTemplateRepositoryContract $templates): void
    {
        $rows = $templates->forSync($this->templateIds);

        if ($rows->isEmpty()) {
            return;
        }

        event(new EmailTemplatesSynced($rows->map(static fn (EmailTemplate $template): array => [
            'event' => $template->event->value,
            'audience' => $template->audience->value,
            'status' => $template->status->value,
            'subject' => $template->getTranslations('subject'),
            'body' => $template->getTranslations('body'),
        ])->values()->all()));

        $templates->markSynced($rows->modelKeys());
    }
}
