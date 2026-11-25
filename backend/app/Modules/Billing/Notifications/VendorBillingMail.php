<?php

namespace App\Modules\Billing\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Billing e-mail to a store owner (on-demand mail route). Uses the admin's active
 * e-mail template of the event (Admin › Settings › Email templates) and falls back
 * to the built-in billing::notifications.<event>.vendor_* texts.
 */
class VendorBillingMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $variables  template variables (see EmailTemplateEvent::variables())
     */
    public function __construct(
        public readonly EmailTemplateEvent $template,
        public readonly string $eventName,
        public readonly array $variables,
        public readonly string $mailLocale,
        public readonly ?string $link = null,
    ) {
        $this->onQueue('mail');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage|TemplatedMail
    {
        $rendered = app(EmailTemplateRenderer::class)->renderEvent($this->template, $this->mailLocale, $this->variables);

        if ($rendered !== null) {
            $recipient = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor('mail') : $notifiable->routeNotificationFor('mail', $this);

            return (new TemplatedMail($rendered))->to($recipient);
        }

        $params = $this->parameters();
        $mail = (new MailMessage)
            ->subject((string) __('billing::notifications.'.$this->eventName.'.vendor_subject', $params, $this->mailLocale))
            ->greeting((string) __('billing::notifications.greeting', ['name' => $this->variables['vendor.name'] ?? ''], $this->mailLocale))
            ->line((string) __('billing::notifications.'.$this->eventName.'.vendor_body', $params, $this->mailLocale));

        return $this->link !== null ? $mail->action((string) __('billing::notifications.open_billing', [], $this->mailLocale), $this->link) : $mail;
    }

    /**
     * Template variables as translation parameters ("invoice.number" → "invoice_number").
     *
     * @return array<string, string>
     */
    private function parameters(): array
    {
        $params = [];

        foreach ($this->variables as $key => $value) {
            $params[str_replace('.', '_', $key)] = (string) $value;
        }

        return $params;
    }
}
