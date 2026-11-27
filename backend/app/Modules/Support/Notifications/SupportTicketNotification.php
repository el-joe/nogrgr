<?php

namespace App\Modules\Support\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Support ticket update for admins or the store owner (database + mail + Reverb via the
 * shared PanelNotification, category "support"). Reply events mail the "Support ticket
 * reply" registry template `support.ticket_reply` (MAIL-037, #124); other events mail the
 * generic panel alert template.
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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::SupportTicketReply, ...parent::mailTemplates()];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return in_array($this->eventName, self::REPLY_EVENTS, true) ? EmailTemplateEvent::SupportTicketReply : parent::mailTemplate($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! in_array($this->eventName, self::REPLY_EVENTS, true)) {
            return parent::toMail($notifiable);
        }

        return $this->templatedMail(EmailTemplateEvent::SupportTicketReply, $this->mailVariables($notifiable));
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
