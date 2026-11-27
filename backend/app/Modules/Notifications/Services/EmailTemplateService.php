<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\EmailTemplateData;
use App\Modules\Notifications\DTOs\RenderedEmailData;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Jobs\SyncEmailTemplatesToTenants;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Admin › Settings › Email templates (ADM-102/102a, decision #118): one row per registry
 * key (created from the code default), ar/en subject + body, enable / disable, reset to
 * default, live preview, "Send test" to the current admin and "Sync to tenants".
 */
class EmailTemplateService extends BaseService
{
    public function __construct(
        private readonly EmailTemplateRepositoryContract $templates,
        private readonly EmailTemplateRenderer $renderer,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->templates->counts();
    }

    /**
     * Gives every registry key a row (created from its code default) so the list shows
     * every e-mail the platform sends. Idempotent; returns the number of rows created.
     */
    public function ensureRegistry(): int
    {
        $existing = $this->templates->existingEvents();
        $created = 0;

        foreach (EmailTemplateEvent::cases() as $event) {
            if (in_array($event->value, $existing, true)) {
                continue;
            }

            $this->templates->create([
                'event' => $event,
                'audience' => $event->audience(),
                'subject' => $this->defaults($event, 'subject'),
                'body' => $this->defaults($event, 'body'),
                'status' => EmailTemplateStatus::Active,
            ]);
            $created++;
        }

        return $created;
    }

    public function update(EmailTemplate $template, EmailTemplateData $data, ?string $editor = null): EmailTemplate
    {
        // Replace (not merge) the translations so a cleared locale is removed.
        $template->replaceTranslations('subject', $data->subject);
        $template->replaceTranslations('body', $data->body);

        /** @var EmailTemplate $updated */
        $updated = $this->templates->update($template, ['status' => $data->status, 'updated_by_name' => $editor]);

        return $updated;
    }

    /**
     * "Reset to default": subject + body of both locales back to the code default.
     */
    public function reset(EmailTemplate $template, ?string $editor = null): EmailTemplate
    {
        $template->replaceTranslations('subject', $this->defaults($template->event, 'subject'));
        $template->replaceTranslations('body', $this->defaults($template->event, 'body'));

        /** @var EmailTemplate $updated */
        $updated = $this->templates->update($template, ['updated_by_name' => $editor]);
        $this->auditor->record('email_templates.reset', $template, ['event' => $template->event->value]);

        return $updated;
    }

    /**
     * Unsaved editor content rendered through the real layout with sample values.
     *
     * @param  array<string, string>  $variables
     */
    public function previewContent(EmailTemplateEvent $event, string $subject, string $body, string $locale, array $variables = []): RenderedEmailData
    {
        return $this->renderer->renderContent($subject, EmailHtmlSanitizer::clean($body), $locale, $variables !== [] ? $variables : $event->variables(), $event);
    }

    /**
     * @return array<string, string>
     */
    private function defaults(EmailTemplateEvent $event, string $field): array
    {
        $values = [];

        foreach (EmailTemplate::LOCALES as $locale) {
            $values[$locale] = $field === 'subject' ? $event->defaultSubject($locale) : $event->defaultBody($locale);
        }

        return $values;
    }

    /**
     * Render with the event's sample values and send now (errors surface to the admin).
     */
    public function sendTest(EmailTemplate $template, string $locale, string $email): void
    {
        $rendered = $this->renderer->render($template, $locale, $template->event->variables());

        try {
            Mail::to($email)->send(new TemplatedMail($rendered));
        } catch (TransportExceptionInterface) {
            throw new DomainException(__('notifications::templates.test_failed'));
        }

        $this->auditor->record('email_templates.test_sent', $template, ['locale' => $rendered->locale]);
    }

    /**
     * Queue the publication of one template (or all) to every tenant.
     */
    public function syncToTenants(?EmailTemplate $template = null): void
    {
        $ids = $template !== null ? [(int) $template->id] : [];

        DB::afterCommit(static fn () => SyncEmailTemplatesToTenants::dispatch($ids));
        $this->auditor->record('email_templates.synced', $template, ['scope' => $template === null ? 'all' : $template->event->value]);
    }
}
