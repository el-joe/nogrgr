<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Database\Factories\EmailTemplateFactory;
use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Platform\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central e-mail template of one event; `subject` / `body` are per-locale (ar, en).
 * The display name is the event label.
 *
 * @property int $id
 * @property EmailTemplateEvent $event
 * @property EmailAudience $audience
 * @property string $subject
 * @property string $body
 * @property EmailTemplateStatus $status
 * @property string|null $updated_by_name
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailTemplate extends Model
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    use HasTranslations;

    public const LOCALES = ['en', 'ar'];

    protected $table = 'email_templates';

    /**
     * @var list<string>
     */
    public array $translatable = ['subject', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['event', 'audience', 'subject', 'body', 'status', 'updated_by_name', 'synced_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => EmailTemplateEvent::class,
            'audience' => EmailAudience::class,
            'status' => EmailTemplateStatus::class,
            'synced_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EmailTemplateFactory
    {
        return EmailTemplateFactory::new();
    }

    public function name(): string
    {
        return $this->event->label();
    }

    /**
     * Locales with both a subject and a body.
     *
     * @return list<string>
     */
    public function completeLocales(): array
    {
        return array_values(array_filter(self::LOCALES, fn (string $locale): bool => trim((string) $this->getTranslation('subject', $locale, false)) !== ''
            && trim(strip_tags((string) $this->getTranslation('body', $locale, false))) !== ''));
    }

    /**
     * @return list<string>
     */
    public function missingLocales(): array
    {
        return array_values(array_diff(self::LOCALES, $this->completeLocales()));
    }
}
