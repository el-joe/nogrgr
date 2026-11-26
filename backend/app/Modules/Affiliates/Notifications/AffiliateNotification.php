<?php

namespace App\Modules\Affiliates\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Affiliate program notifications to the affiliate (panel inbox + Reverb + mail):
 * activated (MAIL-038), conversion approved / reversed (MAIL-039), payout paid /
 * rejected (MAIL-040). Mail = the admin's active template of the event, else the
 * built-in layout (Figma Emails & printables 131:11159 / 11207 / 11262).
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

    public function toMail(object $notifiable): MailMessage
    {
        $locale = app()->getLocale();
        $rendered = app(EmailTemplateRenderer::class)->renderEvent($this->template, $locale, $this->variables);

        if ($rendered !== null) {
            return (new MailMessage)->subject($rendered->subject)->view('affiliates::mail.rendered', ['html' => $rendered->html]);
        }

        return (new MailMessage)
            ->subject((string) __($this->titleKey(), $this->params()))
            ->view('affiliates::mail.affiliate', [
                'locale' => $locale,
                'platform' => (string) config('app.name'),
                'layout' => $this->layout,
                'name' => (string) ($this->variables['affiliate.name'] ?? ''),
                'url' => $this->link,
            ]);
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
