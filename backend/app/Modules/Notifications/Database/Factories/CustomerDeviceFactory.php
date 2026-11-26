<?php

namespace App\Modules\Notifications\Database\Factories;

use App\Modules\Notifications\Enums\DevicePlatform;
use App\Modules\Notifications\Models\CustomerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerDevice>
 */
class CustomerDeviceFactory extends Factory
{
    protected $model = CustomerDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => null,
            'device_id' => (string) Str::uuid(),
            'platform' => DevicePlatform::Android,
            'push_token' => 'fcm-'.Str::random(40),
            'push_enabled' => true,
            'app_version' => '1.0.0',
            'locale' => 'en',
            'last_seen_at' => now(),
        ];
    }
}
