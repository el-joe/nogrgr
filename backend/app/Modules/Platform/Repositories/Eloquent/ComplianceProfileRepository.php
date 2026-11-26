<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<ComplianceProfile>
 */
class ComplianceProfileRepository extends BaseRepository implements ComplianceProfileRepositoryContract
{
    protected function model(): string
    {
        return ComplianceProfile::class;
    }

    public function query(): Builder
    {
        return ComplianceProfile::query()->withoutGlobalScopes();
    }

    public function forTenant(int $tenantId): ?ComplianceProfile
    {
        return $this->query()->where('tenant_id', $tenantId)->with('documents')->first();
    }

    public function listing(array $filters = []): Builder
    {
        $status = ComplianceStatus::tryFrom((string) ($filters['status'] ?? ''));
        $document = ComplianceDocumentType::tryFrom((string) ($filters['document'] ?? ''));
        $countryId = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->select('compliance_profiles.*', 'tenants.name as store_name', 'tenants.slug as store_slug')
            ->join('tenants', 'tenants.id', '=', 'compliance_profiles.tenant_id')
            ->with('documents:id,compliance_profile_id,type,status')
            ->when($status !== null, fn (Builder $query) => $query->where('compliance_profiles.status', $status?->value))
            ->when($status === null, fn (Builder $query) => $query->where('compliance_profiles.status', '!=', ComplianceStatus::Draft->value))
            ->when($document !== null, fn (Builder $query) => $query->whereHas('documents', fn (Builder $query) => $query->where('type', $document?->value)))
            ->when($countryId !== false && $countryId !== null, fn (Builder $query) => $query->where('tenants.default_country_id', $countryId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('tenants.name', 'like', $term)->orWhere('tenants.slug', 'like', $term)->orWhere('compliance_profiles.legal_name', 'like', $term));
            });
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all();

        return $counts;
    }

    public function findDocument(ComplianceProfile $profile, int $documentId): ?ComplianceDocument
    {
        return ComplianceDocument::query()->withoutGlobalScopes()->where('compliance_profile_id', $profile->id)->whereKey($documentId)->first();
    }

    public function updateDocuments(ComplianceProfile $profile, array $attributes): void
    {
        ComplianceDocument::query()->withoutGlobalScopes()->where('compliance_profile_id', $profile->id)->get()
            ->each(fn (ComplianceDocument $document) => $document->fill($attributes)->save());
    }

    public function documentOfType(ComplianceProfile $profile, ComplianceDocumentType $type): ?ComplianceDocument
    {
        return ComplianceDocument::query()->withoutGlobalScopes()->where('compliance_profile_id', $profile->id)->where('type', $type->value)->latest('id')->first();
    }

    public function createDocument(ComplianceProfile $profile, array $attributes): ComplianceDocument
    {
        $document = new ComplianceDocument;
        $document->forceFill(['tenant_id' => $profile->tenant_id, 'compliance_profile_id' => $profile->id])->fill($attributes)->save();

        return $document;
    }

    public function updateDocument(ComplianceDocument $document, array $attributes): ComplianceDocument
    {
        $document->fill($attributes)->save();

        return $document;
    }

    public function deleteDocument(ComplianceDocument $document): void
    {
        $document->delete();
    }
}
