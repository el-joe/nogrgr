<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Services\NotificationInboxService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Shared\Support\RelativeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base of every central panel notification: database (inbox + bell), mail and a live
 * Reverb push, each switched per category by the recipient (NotificationPreferenceService).
 * Titles/bodies are stored as translation keys + params so the inbox follows the
 * reader's language. Database type = "<category>.<event>". Mail renders the generic
 * alert template of the recipient's panel (admin.alert / vendor.alert / affiliate.alert,
 * decision #118) unless a subclass maps a specific template.
 */
abstract class PanelNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    abstract public function category(): NotificationCategory;

    /**
     * Short snake_case event name inside the category ("password_changed").
     */
    abstract public function event(): string;

    abstract protected function titleKey(): string;

    abstract protected function bodyKey(): string;

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return [];
    }

    protected function url(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof Model) {
            return ['database'];
        }

        // Store members keep their switches in the store database (VEN-087), applied per
        // channel by EnforceVendorNotificationPreferences; central users use this matrix.
        $channels = $notifiable instanceof VendorStaff
            ? ['database', 'mail', 'broadcast']
            : app(NotificationPreferenceService::class)->channelsFor($notifiable, $this->category());

        if (! $notifiable instanceof HasPanelInbox) {
            $channels = array_values(array_diff($channels, ['broadcast']));
        }

        if (blank($notifiable->getAttribute('email'))) {
            $channels = array_values(array_diff($channels, ['mail']));
        }

        return $channels;
    }

    public function databaseType(object $notifiable): string
    {
        return $this->category()->value.'.'.$this->event();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category()->value,
            'event' => $this->event(),
            'title_key' => $this->titleKey(),
            'title_params' => $this->params(),
            'body_key' => $this->bodyKey(),
            'body_params' => $this->params(),
            // Stored host-independent (QA-FINAL-008); mails keep the absolute url().
            'url' => RelativeUrl::of($this->url()),
        ];
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return array_values(array_unique([
            EmailTemplateEvent::AdminAlert, EmailTemplateEvent::VendorAlert, EmailTemplateEvent::AffiliateAlert,
            ...array_values(static::specificMailTemplates()),
        ], SORT_REGULAR));
    }

    /**
     * event() => its own editable template (decision #124 batch 2); events not listed use
     * the generic alert template of the recipient's panel.
     *
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        $specific = static::specificMailTemplates()[$this->event()] ?? null;

        if ($specific !== null) {
            return $specific;
        }

        return match (true) {
            $notifiable instanceof Admin => EmailTemplateEvent::AdminAlert,
            $notifiable instanceof Affiliate => EmailTemplateEvent::AffiliateAlert,
            default => EmailTemplateEvent::VendorAlert,
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail($this->mailTemplate($notifiable), $this->alertVariables($notifiable));
    }

    /**
     * Variables of the generic alert templates: the inbox title / body in the mail locale.
     *
     * @return array<string, string>
     */
    protected function alertVariables(object $notifiable): array
    {
        $store = $this->params()['store'] ?? null;

        return [
            ...(is_string($store) && $store !== '' && $store !== '—' ? ['store.name' => $store] : []),
            'recipient.name' => (string) ($notifiable->name ?? ''),
            'alert.title' => $this->text($this->titleKey()),
            'alert.body' => $this->text($this->bodyKey()),
            'action.url' => (string) $this->url(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $record = new PanelNotificationRecord;
        $record->forceFill([
            'id' => $this->id,
            'type' => $this->databaseType($notifiable),
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now(),
        ]);

        $surface = $notifiable instanceof HasPanelInbox ? $notifiable->panelSurface() : 'admin';

        return new BroadcastMessage(['item' => app(NotificationInboxService::class)->present($record, $surface)]);
    }

    public function broadcastType(): string
    {
        return 'panel.notification';
    }

    protected function text(string $key): string
    {
        $text = __($key, $this->params());

        return is_string($text) ? $text : $key;
    }
}
