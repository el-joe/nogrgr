<?php

namespace App\Modules\AfterSales\Models;

use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\Commerce\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Timeline entry of a return: a status change, an internal note or a message to the
 * customer (customer_visible).
 *
 * @property int $id
 * @property int $return_request_id
 * @property ReturnEventType $type
 * @property ReturnStatus|null $from_status
 * @property ReturnStatus|null $to_status
 * @property string|null $note
 * @property bool $customer_visible
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property Carbon|null $created_at
 */
class ReturnRequestEvent extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['return_request_id', 'type', 'from_status', 'to_status', 'note', 'customer_visible', 'actor_type', 'actor_id', 'actor_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReturnEventType::class,
            'from_status' => ReturnStatus::class,
            'to_status' => ReturnStatus::class,
            'customer_visible' => 'boolean',
            'actor_type' => ActorType::class,
            'actor_id' => 'integer',
        ];
    }
}
