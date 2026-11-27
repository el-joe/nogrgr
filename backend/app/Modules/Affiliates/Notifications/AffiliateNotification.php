<?php

namespace App\Modules\Affiliates\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Affiliate program notifications to the affiliate (panel inbox + Reverb + mail):
 * activated (MAIL-038), conversion approved / reversed (MAIL-039), payout paid /
 * rejected (MAIL-040). Mail = the registry template of the event (#124) in the MAIL-001
 * layout (Figma Emails & printables 131:11159 / 11207 / 11262).
 *
 * @phpstan-type MailRow array{label: string, value: string}
 */
class AffiliateNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters  title / body parameters
     * @param  array<string, string>  $variables  template variables of $template
     * @param  array{tone: string, icon: string, label: string, heading: string, rows: list<array{label: string, value: string}>, note: string|null, cta: string|null}  $layout
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly EmailTemplateEvent $template,
        public readonly array $variables,
        public readonly array $layout,
        public readonly ?string $link = null,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return $this->eventName === 'activated' ? NotificationCategory::System : NotificationCategory::Payments;
    }

    public function event(): string
    {
        return 'affiliate_'.$this->eventName;
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::AffiliateActivated, EmailTemplateEvent::AffiliateConversion, EmailTemplateEvent::AffiliatePayout];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return $this->template;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail($this->template, $this->variables);
    }

    protected function titleKey(): string
    {
        return 'affiliates::notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'affiliates::notifications.'.$this->eventName.'.body';
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
}
