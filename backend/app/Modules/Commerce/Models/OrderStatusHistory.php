<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One status change of an order (timeline / tracking).
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    /**
     * @var list<string>
     */
    protected $fillable = ['order_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'actor_type' => ActorType::class,
            'actor_id' => 'integer',
        ];
    }
}
