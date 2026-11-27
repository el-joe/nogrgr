<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Shared\Support\RelativeUrl;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A central user's inbox row (Laravel `notifications` table on the central DB).
 * `data` = PanelNotification::toArray(): category, icon, title/body translation keys
 * + params, url.
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PanelNotificationRecord extends DatabaseNotification
{
    use CentralConnection;

    public function category(): NotificationCategory
    {
        return NotificationCategory::fromType($this->type) ?? NotificationCategory::System;
    }

    public function title(): string
    {
        return $this->translated('title');
    }

    public function body(): string
    {
        return $this->translated('body');
    }

    /**
     * The notification link resolved on the reader's host: rows store a relative path
     * (QA-FINAL-008); older rows with an absolute panel URL are shortened the same way.
     */
    public function targetUrl(): ?string
    {
        $url = $this->data['url'] ?? null;

        return is_string($url) && $url !== '' ? RelativeUrl::of($url) : null;
    }

    private function translated(string $field): string
    {
        $key = $this->data[$field.'_key'] ?? null;
        $params = $this->data[$field.'_params'] ?? [];

        if (! is_string($key) || $key === '') {
            return (string) ($this->data[$field] ?? '');
        }

        $text = __($key, is_array($params) ? $params : []);

        return is_string($text) ? $text : $key;
    }
}
