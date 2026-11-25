<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Central DB (compliance_profiles + compliance_documents).
 *
 * @extends RepositoryContract<ComplianceProfile>
 */
interface ComplianceProfileRepositoryContract extends RepositoryContract
{
    public function forTenant(int $tenantId): ?ComplianceProfile;

    /**
     * Filters: status, document (type), country_id, search (store name / slug).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ComplianceProfile>
     */
    public function listing(array $filters = []): Builder;

    /**
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array;

    public function findDocument(ComplianceProfile $profile, int $documentId): ?ComplianceDocument;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDocuments(ComplianceProfile $profile, array $attributes): void;
}
