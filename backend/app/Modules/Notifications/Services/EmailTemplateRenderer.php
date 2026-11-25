<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\RenderedEmailData;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;

/**
 * Renders a template for one locale: `{{ var.name }}` placeholders are replaced
 * (HTML-escaped in the body, plain in the subject; unknown ones become empty) and the
 * body is wrapped in the shared e-mail layout. A missing locale falls back to English.
 */
class EmailTemplateRenderer
{
    public const PLACEHOLDER = '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i';

    public function __construct(private readonly EmailTemplateRepositoryContract $templates) {}

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public function render(EmailTemplate $template, string $locale, array $variables): RenderedEmailData
    {
        $locale = in_array($locale, $template->completeLocales(), true) ? $locale : ($template->completeLocales()[0] ?? 'en');
        $subject = (string) $template->getTranslation('subject', $locale, false);
        $body = (string) $template->getTranslation('body', $locale, false);

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
     * The active template of an event, or null (callers fall back to their default text).
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderEvent(EmailTemplateEvent $event, string $locale, array $variables): ?RenderedEmailData
    {
        $template = $this->templates->findByEvent($event);

        if ($template === null || $template->status !== EmailTemplateStatus::Active || $template->completeLocales() === []) {
            return null;
        }

        return $this->render($template, $locale, ['platform.name' => (string) config('app.name'), ...$variables]);
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
