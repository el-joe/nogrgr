<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A store's copy of a shopper e-mail template (tenant DB, VEN-080): kept in step with
 * the platform template until the store customises it (is_customized), then it wins
 * for that store's e-mails (EmailTemplateRenderer::renderEvent).
 *
 * @property int $id
 * @property EmailTemplateEvent $event
 * @property string $subject
 * @property string $body
 * @property EmailTemplateStatus $status
 * @property string|null $updated_by_name
 * @property bool $is_customized
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreEmailTemplate extends Model implements AiTranslatable
{
    use HasTranslations;

    protected $table = 'store_email_templates';

    /**
     * @var list<string>
     */
    public array $translatable = ['subject', 'body'];

    /**
     * Fields the paid store translation run translates (decision #44).
     *
     * @return list<string>
     */
    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @var list<string>
     */
    protected $fillable = ['event', 'subject', 'body', 'status', 'is_customized', 'updated_by_name', 'synced_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => EmailTemplateEvent::class,
            'status' => EmailTemplateStatus::class,
            'is_customized' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Locales with both a subject and a body.
     *
     * @return list<string>
     */
    public function completeLocales(): array
    {
        return array_values(array_filter(EmailTemplate::LOCALES, fn (string $locale): bool => trim((string) $this->getTranslation('subject', $locale, false)) !== ''
            && trim(strip_tags((string) $this->getTranslation('body', $locale, false))) !== ''));
    }

    /**
     * Used for this store's e-mails: customised, or an active platform copy.
     */
    public function isUsable(): bool
    {
        return $this->completeLocales() !== [] && ($this->is_customized || $this->status === EmailTemplateStatus::Active);
    }
}
