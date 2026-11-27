<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\RenderedEmailData;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\StoreEmailTemplateRepositoryContract;
use App\Modules\Notifications\Support\MailBodyStyler;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use Illuminate\Database\QueryException;

/**
 * Renders e-mails from the template registry (decision #118). Content per locale:
 * the store's own template (store-scoped keys inside a store, VEN-080) → the admin
 * template (`email_templates`) → the code default (EmailTemplateEvent::defaultBody).
 * `{{ var.name }}` placeholders are replaced (HTML-escaped in the body, plain in the
 * subject; unknown ones become empty) and the body is wrapped in the MAIL-001 layout
 * (central or store variant, RTL for Arabic).
 */
class EmailTemplateRenderer
{
    public const PLACEHOLDER = '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i';

    public function __construct(
        private readonly EmailTemplateRepositoryContract $templates,
        private readonly StoreEmailTemplateRepositoryContract $storeTemplates,
        private readonly MailLayoutService $layout,
    ) {}

    /**
     * One stored template; a locale it lacks uses the code default of that locale.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function render(EmailTemplate|StoreEmailTemplate $template, string $locale, array $variables): RenderedEmailData
    {
        $locale = in_array($locale, EmailTemplate::LOCALES, true) ? $locale : 'en';

        if (! in_array($locale, $template->completeLocales(), true)) {
            return $this->renderDefault($template->event, $locale, $variables);
        }

        return $this->renderContent(
            (string) $template->getTranslation('subject', $locale, false),
            (string) $template->getTranslation('body', $locale, false),
            $locale,
            $variables,
            $template->event,
        );
    }

    /**
     * The registry default of an event (Reset to default, previews of new templates).
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderDefault(EmailTemplateEvent $event, string $locale, array $variables): RenderedEmailData
    {
        return $this->renderContent($event->defaultSubject($locale), $event->defaultBody($locale), $locale, $variables, $event);
    }

    /**
     * Any subject / body (unsaved editor content, previews) with the given values.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderContent(string $subject, string $body, string $locale, array $variables, ?EmailTemplateEvent $event = null): RenderedEmailData
    {
        $locale = in_array($locale, EmailTemplate::LOCALES, true) ? $locale : 'en';
        $variables = $this->withContext($variables);
        $layout = $this->layout->forMail($locale);

        if ($event !== null && ! $layout->isStore) {
            $layout = $layout->withLabel((string) __('notifications::mail_layout.labels.'.$event->audience()->value, [], $locale));
        }

        $subject = trim($this->replace($subject, $variables, false));
        $html = MailBodyStyler::style($this->replace($body, $variables, true), $layout->primaryColor, $locale === 'ar', ! $layout->isStore);

        return new RenderedEmailData(
            subject: $subject,
            html: view('notifications::mail.template', [
                'body' => $html,
                'subject' => $subject,
                'preheader' => MailBodyStyler::preheader($html),
                'locale' => $locale,
                'layout' => $layout,
            ])->render(),
            locale: $locale,
        );
    }

    /**
     * The e-mail of an event as it is sent now, or null when the template is disabled.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderEvent(EmailTemplateEvent $event, string $locale, array $variables): ?RenderedEmailData
    {
        return $this->isEnabled($event) ? $this->compose($event, $locale, $variables) : null;
    }

    /**
     * The e-mail of an event, whatever its switch (the switch is applied when sending).
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function compose(EmailTemplateEvent $event, string $locale, array $variables): RenderedEmailData
    {
        $locale = in_array($locale, EmailTemplate::LOCALES, true) ? $locale : 'en';
        $store = $this->storeTemplate($event);

        if ($store !== null && $store->isUsable() && in_array($locale, $store->completeLocales(), true)) {
            return $this->render($store, $locale, $variables);
        }

        $template = $this->platformTemplate($event);

        if ($template !== null && in_array($locale, $template->completeLocales(), true)) {
            return $this->render($template, $locale, $variables);
        }

        return $this->renderDefault($event, $locale, $variables);
    }

    /**
     * Enable / disable switch: a store's customised copy decides for that store, else
     * the admin template; an event nobody edited yet is enabled; mandatory account /
     * security e-mails are always enabled.
     */
    public function isEnabled(EmailTemplateEvent $event): bool
    {
        if ($event->isMandatory()) {
            return true;
        }

        $store = $this->storeTemplate($event);

        if ($store !== null && $store->is_customized) {
            return $store->status === EmailTemplateStatus::Active;
        }

        $template = $this->platformTemplate($event);

        return $template === null || $template->status === EmailTemplateStatus::Active;
    }

    private function platformTemplate(EmailTemplateEvent $event): ?EmailTemplate
    {
        try {
            return $this->templates->findByEvent($event);
        } catch (QueryException) {
            return null;
        }
    }

    private function storeTemplate(EmailTemplateEvent $event): ?StoreEmailTemplate
    {
        if (! tenancy()->initialized || ! $event->isStoreScoped()) {
            return null;
        }

        try {
            return $this->storeTemplates->findByEvent($event);
        } catch (QueryException) {
            return null; // store not migrated yet
        }
    }

    /**
     * Platform name always; store name / address inside a store unless given.
     *
     * @param  array<string, string|int|float|null>  $variables
     * @return array<string, string|int|float|null>
     */
    private function withContext(array $variables): array
    {
        $context = ['platform.name' => (string) config('app.name')];
        $tenant = tenancy()->initialized ? tenant() : null;

        if ($tenant instanceof Tenant) {
            $context['store.name'] = (string) $tenant->name;
            $context['store.url'] = StoreSettingsService::storeUrl($tenant);
        }

        return [...$context, ...$variables];
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    private function replace(string $text, array $variables, bool $escape): string
    {
        return (string) preg_replace_callback(self::PLACEHOLDER, static function (array $match) use ($variables, $escape): string {
            $value = (string) ($variables[$match[1]] ?? '');

            return $escape ? e($value) : $value;
        }, $text);
    }
}
