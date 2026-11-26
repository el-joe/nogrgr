<?php

namespace App\Modules\Support\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Support ticket update for admins or the store owner (database + mail + Reverb via the
 * shared PanelNotification, category "support"). Reply events mail the "Support ticket
 * reply" e-mail (MAIL-037): the admin's active template `support.ticket_reply` when there
 * is one, else the built-in branded layout support::mail.ticket-reply.
 * Texts: support::tickets.notifications.<event>.
 */
class SupportTicketNotification extends PanelNotification
{
    public const REPLY_EVENTS = ['admin_replied', 'requester_replied'];

    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly ?string $link = null,
        public readonly bool $withMail = true,
    ) {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        $channels = parent::via($notifiable);

        return $this->withMail ? $channels : array_values(array_diff($channels, ['mail']));
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Support;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! in_array($this->eventName, self::REPLY_EVENTS, true)) {
            return parent::toMail($notifiable);
        }

        $locale = app()->getLocale();
        $variables = $this->mailVariables($notifiable);
        $rendered = app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::SupportTicketReply, $locale, $variables);

        if ($rendered !== null) {
            // Already rendered + sanitised by the template engine (same output as TemplatedMail).
            return (new MailMessage)->subject($rendered->subject)->view('support::mail.rendered', ['html' => $rendered->html]);
        }

        return (new MailMessage)
            ->subject((string) __('support::tickets.mail.subject', ['reference' => $this->parameters['reference'] ?? '', 'subject' => $this->parameters['subject'] ?? '']))
            ->view('support::mail.ticket-reply', [
                'locale' => $locale,
                'variables' => $variables,
                'platform' => (string) config('app.name'),
            ]);
    }

    protected function titleKey(): string
    {
        return 'support::tickets.notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'support::tickets.notifications.'.$this->eventName.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return $this->parameters;
    }

    protected function url(): ?string
    {
        return $this->link;
    }

    /**
     * Template variables of EmailTemplateEvent::SupportTicketReply.
     *
     * @return array<string, string>
     */
    protected function mailVariables(object $notifiable): array
    {
        $name = $notifiable instanceof AnonymousNotifiable ? '' : (string) ($notifiable->name ?? '');

        return [
            'platform.name' => (string) config('app.name'),
            'store.name' => (string) ($this->parameters['store'] ?? ''),
            'recipient.name' => $name,
            'ticket.reference' => (string) ($this->parameters['reference'] ?? ''),
            'ticket.subject' => (string) ($this->parameters['subject'] ?? ''),
            'reply.author' => (string) ($this->parameters['author'] ?? ''),
            'reply.excerpt' => (string) ($this->parameters['excerpt'] ?? ''),
            'ticket.url' => (string) ($this->mailLink() ?? ''),
        ];
    }

    protected function mailLink(): ?string
    {
        return $this->link;
    }
}
