<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Models\DnsRecordTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DnsRecordTemplate>
 */
class DnsRecordTemplateFactory extends Factory
{
    protected $model = DnsRecordTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => DnsRecordType::CNAME,
            'host' => 'www',
            'value' => '{subdomain}',
            'ttl' => 3600,
            'priority' => null,
            'sort_order' => 100,
        ];
    }
}
