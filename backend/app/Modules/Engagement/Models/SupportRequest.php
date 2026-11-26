<?php

namespace App\Modules\Engagement\Models;

use App\Modules\Engagement\Enums\SupportRequestStatus;
use App\Modules\Engagement\Enums\SupportSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A storefront contact / support request (tenant DB, SF-ECO-035). Phone in E.164.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $order_number
 * @property SupportSubject $subject
 * @property list<string>|null $attachments paths on the store's private `local` disk
 * @property string $message
 * @property SupportRequestStatus $status
 * @property string|null $locale
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SupportRequest extends Model
{
    protected $table = 'support_requests';

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_id', 'name', 'email', 'phone', 'order_number', 'subject', 'message', 'attachments', 'status', 'locale', 'ip_address'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => SupportRequestStatus::class, 'subject' => SupportSubject::class, 'attachments' => 'array'];
    }

    public function ticketNumber(): string
    {
        return 'SR-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
