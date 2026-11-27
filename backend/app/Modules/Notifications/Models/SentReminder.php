<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One sent scheduled reminder (central idempotency log, decision #124).
 *
 * @property int $id
 * @property string $reminder
 * @property string $subject
 * @property string $period
 * @property Carbon $sent_at
 */
class SentReminder extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $table = 'sent_reminders';

    /**
     * @var list<string>
     */
    protected $fillable = ['reminder', 'subject', 'period', 'sent_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
