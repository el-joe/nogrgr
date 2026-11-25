<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\DnsCheckStatus;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\SslStatus;
use App\Modules\Platform\Models\DomainRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DomainRequest>
 */
class DomainRequestFactory extends Factory
{
    protected $model = DomainRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host' => Str::lower(Str::random(8)).'.example.com',
            'status' => DomainRequestStatus::Pending,
            'verification_token' => Str::lower(Str::random(32)),
            'dns_status' => DnsCheckStatus::Unchecked,
            'ssl_status' => SslStatus::Unknown,
        ];
    }
}
