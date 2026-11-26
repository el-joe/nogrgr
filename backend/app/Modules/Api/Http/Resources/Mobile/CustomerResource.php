<?php

namespace App\Modules\Api\Http\Resources\Mobile;

use App\Modules\Identity\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in customer (Mobile API `me`, sign-in / sign-up responses).
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'phone_country' => $this->phone_country,
            'locale' => $this->locale,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
