<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One shipping progress step of an order (tracking timeline).
 *
 * @property int $id
 * @property int $order_id
 * @property FulfilmentStatus $status
 * @property string|null $location
 * @property string|null $note
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property Carbon|null $created_at
 */
class OrderTrackingEvent extends Model
{
    /**
     * Explicit storage format: admin pages read store orders after leaving the store's
     * tenant context, where the "tenant" connection (default date format) is gone.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['order_id', 'status', 'location', 'note', 'actor_type', 'actor_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FulfilmentStatus::class,
            'actor_type' => ActorType::class,
            'actor_id' => 'integer',
        ];
    }
}
