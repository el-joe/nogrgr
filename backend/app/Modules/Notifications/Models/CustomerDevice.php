<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Database\Factories\CustomerDeviceFactory;
use App\Modules\Notifications\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A mobile app install of the store (tenant DB) and its FCM registration token.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $device_id
 * @property DevicePlatform $platform
 * @property string|null $push_token
 * @property bool $push_enabled
 * @property string|null $app_version
 * @property string|null $os_version
 * @property string|null $model
 * @property string|null $locale
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerDevice extends Model
{
    /** @use HasFactory<CustomerDeviceFactory> */
    use HasFactory;

    protected $table = 'customer_devices';

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_id', 'device_id', 'platform', 'push_token', 'push_enabled', 'app_version', 'os_version', 'model', 'locale', 'last_seen_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['push_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'push_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function newFactory(): CustomerDeviceFactory
    {
        return CustomerDeviceFactory::new();
    }
}
