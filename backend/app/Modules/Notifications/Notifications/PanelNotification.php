<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Services\NotificationInboxService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
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
 * reader's language. Database type = "<category>.<event>".
 */
abstract class PanelNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

        $channels = app(NotificationPreferenceService::class)->channelsFor($notifiable, $this->category());

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
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->text($this->titleKey()))
            ->line($this->text($this->bodyKey()));

        $url = $this->url();

        return $url !== null ? $mail->action(__('notifications::panel.mail.open'), $url) : $mail;
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

    private function text(string $key): string
    {
        $text = __($key, $this->params());

        return is_string($text) ? $text : $key;
    }
}
