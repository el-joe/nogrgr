<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\RenderedEmailData;
use App\Modules\Notifications\DTOs\StoreEmailTemplateData;
use App\Modules\Notifications\DTOs\StoreEmailTemplateRow;
use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\StoreEmailTemplateRepositoryContract;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendor › Settings › Email templates (VEN-080 / 080a, tenant context): the shopper
 * e-mails (order and account events) with the platform's template, the store's own
 * customised version, "reset to default" (back to the platform template) and "sync from
 * platform" (refresh every copy the store did not customise). The platform's
 * "Sync to tenants" does the same for every store (SyncStoreEmailTemplates).
 */
class StoreEmailTemplateService
{
    public function __construct(
        private readonly StoreEmailTemplateRepositoryContract $store,
        private readonly EmailTemplateRepositoryContract $platform,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function events(): array
    {
        return EmailTemplateEvent::forAudience(EmailAudience::Shoppers);
    }

    public static function supports(EmailTemplateEvent $event): bool
    {
        return in_array($event, self::events(), true);
    }

    /**
     * @return Collection<int, StoreEmailTemplateRow>
     */
    public function rows(): Collection
    {
        $own = $this->store->allByEvent();

        return collect(self::events())->map(fn (EmailTemplateEvent $event): StoreEmailTemplateRow => new StoreEmailTemplateRow(
            $event,
            $this->platform->findByEvent($event),
            $own->get($event->value),
        ));
    }

    public function row(EmailTemplateEvent $event): StoreEmailTemplateRow
    {
        abort_unless(self::supports($event), 404);

        return new StoreEmailTemplateRow($event, $this->platform->findByEvent($event), $this->store->findByEvent($event));
    }

    public function customize(EmailTemplateEvent $event, StoreEmailTemplateData $data): StoreEmailTemplate
    {
        abort_unless(self::supports($event), 404);

        $subject = array_filter(array_map('trim', $data->subject), static fn (string $line): bool => $line !== '');
        $body = array_map(static fn (string $html): string => EmailHtmlSanitizer::clean($html), array_filter($data->body, static fn (string $html): bool => trim(strip_tags($html)) !== ''));

        return DB::transaction(function () use ($event, $subject, $body): StoreEmailTemplate {
            $template = $this->store->findByEvent($event) ?? new StoreEmailTemplate(['event' => $event, 'status' => EmailTemplateStatus::Active]);
            $template->replaceTranslations('subject', $subject);
            $template->replaceTranslations('body', $body);
            $template->is_customized = true;
            $template->save();

            return $template;
        });
    }

    /**
     * "Reset to default": drop the store's version; the platform template applies again.
     */
    public function reset(EmailTemplateEvent $event): void
    {
        abort_unless(self::supports($event), 404);

        $platform = $this->platform->findByEvent($event);
        $own = $this->store->findByEvent($event);

        if ($platform === null) {
            if ($own !== null) {
                $this->store->delete($own);
            }

            return;
        }

        $this->copy($event, $platform->getTranslations('subject'), $platform->getTranslations('body'), $platform->status, force: true);
    }

    /**
     * "Sync from platform": refresh every non-customised copy.
     *
     * @return int templates refreshed
     */
    public function syncFromPlatform(): int
    {
        $count = 0;

        foreach (self::events() as $event) {
            $platform = $this->platform->findByEvent($event);

            if ($platform !== null && $this->copy($event, $platform->getTranslations('subject'), $platform->getTranslations('body'), $platform->status)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Copies one platform template into this store unless the store customised it
     * (or $force). Returns whether the copy was written.
     *
     * @param  array<string, string>  $subject
     * @param  array<string, string>  $body
     */
    public function copy(EmailTemplateEvent $event, array $subject, array $body, EmailTemplateStatus $status, bool $force = false): bool
    {
        if (! self::supports($event)) {
            return false;
        }

        $template = $this->store->findByEvent($event) ?? new StoreEmailTemplate(['event' => $event]);

        if ($template->exists && $template->is_customized && ! $force) {
            return false;
        }

        $template->replaceTranslations('subject', $subject);
        $template->replaceTranslations('body', $body);
        $template->status = $status;
        $template->is_customized = false;
        $template->synced_at = now();
        $template->save();

        return true;
    }

    /**
     * Sample values of the event with this store's name and address.
     *
     * @return array<string, string>
     */
    public function sampleVariables(EmailTemplateEvent $event): array
    {
        $tenant = tenant();
        $variables = $event->variables();

        if ($tenant instanceof Tenant) {
            $variables['store.name'] = (string) $tenant->name;
            $variables['store.url'] = StoreSettingsService::storeUrl($tenant);
        }

        return $variables;
    }

    public function preview(EmailTemplateEvent $event, string $locale): ?RenderedEmailData
    {
        $content = $this->row($event)->content();

        return $content !== null && $content->completeLocales() !== []
            ? $this->renderer->render($content, $locale, $this->sampleVariables($event))
            : null;
    }
}
