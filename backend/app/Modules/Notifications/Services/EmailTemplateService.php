<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\EmailTemplateData;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Jobs\SyncEmailTemplatesToTenants;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Admin › Settings › Email templates (ADM-102/102a): one template per event, ar/en
 * subject + body, "Send test" with sample data and "Sync to tenants" (queued job).
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
     * Events without a template yet (create form), grouped by audience label.
     *
     * @return array<string, array<string, string>>
     */
    public function availableEvents(): array
    {
        $existing = $this->templates->existingEvents();
        $groups = [];

        foreach (EmailTemplateEvent::cases() as $event) {
            if (! in_array($event->value, $existing, true)) {
                $groups[$event->audience()->label()][$event->value] = $event->label().' ('.$event->value.')';
            }
        }

        return $groups;
    }

    public function create(EmailTemplateData $data): EmailTemplate
    {
        if ($this->templates->findByEvent($data->event) !== null) {
            throw new DomainException(__('notifications::templates.event_taken'));
        }

        /** @var EmailTemplate $template */
        $template = $this->templates->create([
            'event' => $data->event,
            'audience' => $data->event->audience(),
            'subject' => $data->subject,
            'body' => $data->body,
            'status' => $data->status,
        ]);

        return $template;
    }

    public function update(EmailTemplate $template, EmailTemplateData $data): EmailTemplate
    {
        // Replace (not merge) the translations so a cleared locale is removed.
        $template->replaceTranslations('subject', $data->subject);
        $template->replaceTranslations('body', $data->body);

        /** @var EmailTemplate $updated */
        $updated = $this->templates->update($template, ['status' => $data->status]);

        return $updated;
    }

    public function delete(EmailTemplate $template): void
    {
        $this->templates->delete($template);
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
