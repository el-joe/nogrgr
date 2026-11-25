<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Models\ComplianceDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceDocument>
 */
class ComplianceDocumentFactory extends Factory
{
    protected $model = ComplianceDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ComplianceDocumentType::CommercialRegistration,
            'status' => ComplianceDocumentStatus::Review,
            'document_number' => fake()->numerify('10########'),
            'holder_name' => fake()->company(),
            'expires_at' => now()->addYears(2)->toDateString(),
            'matches_owner' => true,
            'disk' => 'local',
            'path' => null,
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
