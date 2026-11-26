<?php

namespace App\Modules\Support\Models;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Owner;
use App\Modules\Support\Database\Factories\SupportTicketFactory;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Support ticket (central). In a store's context the tenant scope limits it to that store;
 * owner-portal tickets carry owner_id (and tenant_id when a store was picked).
 *
 * @property int $id
 * @property string|null $reference
 * @property int|null $tenant_id
 * @property int|null $owner_id
 * @property TicketAuthor $opened_by_type
 * @property int|null $opened_by_id
 * @property string $opened_by_name
 * @property string|null $opened_by_email
 * @property string $subject
 * @property TicketCategory $category
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $assigned_admin_id
 * @property TicketAuthor|null $last_reply_by
 * @property Carbon|null $last_reply_at
 * @property bool $admin_unread
 * @property bool $requester_unread
 * @property Carbon|null $sla_due_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Admin|null $assignee
 * @property-read Owner|null $owner
 * @property-read Collection<int, SupportTicketMessage> $messages
 */
class SupportTicket extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected $table = 'support_tickets';

    protected $guarded = ['id'];

    protected static function newFactory(): SupportTicketFactory
    {
        return SupportTicketFactory::new();
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    /**
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function displayReference(): string
    {
        return $this->reference ?? 'T-'.$this->id;
    }

    public function title(): string
    {
        return $this->displayReference().' · '.$this->subject;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_by_type' => TicketAuthor::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'last_reply_by' => TicketAuthor::class,
            'last_reply_at' => 'datetime',
            'admin_unread' => 'boolean',
            'requester_unread' => 'boolean',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
