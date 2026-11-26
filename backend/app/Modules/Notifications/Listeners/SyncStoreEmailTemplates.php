<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Events\EmailTemplatesSynced;
use App\Modules\Notifications\Services\StoreEmailTemplateService;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Platform "Sync to tenants" (EmailTemplatesSynced): copies the shopper templates into
 * every active store's `store_email_templates`, keeping the ones a store customised.
 * Queued; one failing store is reported and the others continue.
 */
class SyncStoreEmailTemplates implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly StoreEmailTemplateService $templates) {}

    public function handle(EmailTemplatesSynced $event): void
    {
        $rows = array_values(array_filter($event->templates, static fn (array $row): bool => ($case = EmailTemplateEvent::tryFrom($row['event'])) !== null
            && StoreEmailTemplateService::supports($case)));

        if ($rows === []) {
            return;
        }

        Tenant::query()->where('status', TenantStatus::Active->value)->orderBy('id')->each(function (Tenant $tenant) use ($rows): void {
            try {
                $tenant->run(function () use ($rows): void {
                    foreach ($rows as $row) {
                        $this->templates->copy(
                            EmailTemplateEvent::from($row['event']),
                            $row['subject'],
                            $row['body'],
                            EmailTemplateStatus::tryFrom($row['status']) ?? EmailTemplateStatus::Active,
                        );
                    }
                });
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
