<?php

namespace App\Modules\Notifications\Concerns;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * toMail() helper of TemplatedMailNotification classes: renders the registry template
 * (store → admin → default, MAIL-001 layout) into a MailMessage.
 */
trait RendersMailTemplate
{
    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    protected function templatedMail(EmailTemplateEvent $event, array $variables, ?string $locale = null): MailMessage
    {
        $rendered = app(EmailTemplateRenderer::class)->compose($event, $locale ?? app()->getLocale(), $variables);

        return (new MailMessage)->subject($rendered->subject)->view('notifications::mail.rendered', ['html' => $rendered->html]);
    }

    protected function recipientLocale(object $notifiable): string
    {
        if ($notifiable instanceof HasLocalePreference && is_string($locale = $notifiable->preferredLocale()) && $locale !== '') {
            return $locale;
        }

        $locale = $notifiable->locale ?? null;

        return is_string($locale) && $locale !== '' ? $locale : app()->getLocale();
    }
}
