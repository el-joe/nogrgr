<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\ComplianceRisk;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Models\ComplianceProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceProfile>
 */
class ComplianceProfileFactory extends Factory
{
    protected $model = ComplianceProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ComplianceStatus::NeedsReview,
            'risk' => ComplianceRisk::Low,
            'legal_name' => fake()->company().' Trading',
            'bank_name' => 'Al Rajhi Bank',
            'iban' => 'SA0380000000608010167519',
            'account_name' => fake()->company(),
            'terms_version' => 'v1.0',
            'terms_accepted_at' => now()->subDay(),
            'terms_accepted_ip' => '203.0.113.10',
            'submitted_at' => now()->subHour(),
        ];
    }
}
