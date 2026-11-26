<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\RenderedEmailData;
use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\StoreEmailTemplateRepositoryContract;
use Illuminate\Database\QueryException;

/**
 * Renders a template for one locale: `{{ var.name }}` placeholders are replaced
 * (HTML-escaped in the body, plain in the subject; unknown ones become empty) and the
 * body is wrapped in the shared e-mail layout. A missing locale falls back to English.
 * Inside a store (tenant context) a shopper e-mail uses the store's own template when it
 * has a usable one (VEN-080, StoreEmailTemplate), otherwise the platform template.
 */
class EmailTemplateRenderer
{
    public const PLACEHOLDER = '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i';

    public function __construct(
        private readonly EmailTemplateRepositoryContract $templates,
        private readonly StoreEmailTemplateRepositoryContract $storeTemplates,
    ) {}

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public function render(EmailTemplate|StoreEmailTemplate $template, string $locale, array $variables): RenderedEmailData
    {
        $complete = $template->completeLocales();
        $locale = in_array($locale, $complete, true) ? $locale : ($complete[0] ?? 'en');

        return $this->renderContent(
            (string) $template->getTranslation('subject', $locale, false),
            (string) $template->getTranslation('body', $locale, false),
            $locale,
            $variables,
        );
    }

    /**
     * Unsaved subject / body (vendor preview) rendered with the event's sample values.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderContent(string $subject, string $body, string $locale, array $variables): RenderedEmailData
    {
        return new RenderedEmailData(
            subject: trim($this->replace($subject, $variables, false)),
            html: view('notifications::mail.template', [
                'body' => $this->replace($body, $variables, true),
                'locale' => $locale,
                'platform' => (string) ($variables['platform.name'] ?? config('app.name')),
                'header' => (string) ($variables['store.name'] ?? $variables['platform.name'] ?? config('app.name')),
            ])->render(),
            locale: $locale,
        );
    }

    /**
     * The store's usable template of the event, else the active platform template, or
     * null (callers fall back to their default text).
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderEvent(EmailTemplateEvent $event, string $locale, array $variables): ?RenderedEmailData
    {
        $variables = ['platform.name' => (string) config('app.name'), ...$variables];
        $store = $this->storeTemplate($event);

        if ($store !== null) {
            return $this->render($store, $locale, $variables);
        }

        $template = $this->templates->findByEvent($event);

        if ($template === null || $template->status !== EmailTemplateStatus::Active || $template->completeLocales() === []) {
            return null;
        }

        return $this->render($template, $locale, $variables);
    }

    private function storeTemplate(EmailTemplateEvent $event): ?StoreEmailTemplate
    {
        if (! tenancy()->initialized || $event->audience() !== EmailAudience::Shoppers) {
            return null;
        }

        try {
            $template = $this->storeTemplates->findByEvent($event);
        } catch (QueryException) {
            return null; // store not migrated yet
        }

        return $template?->isUsable() ? $template : null;
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
