<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\DTOs\ComplianceDecisionData;
use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceRisk;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Events\ComplianceReviewed;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store KYC review (central DB). Admin decisions: verified (all documents verified),
 * needs action / rejected (note required, documents flagged). Every decision fires
 * ComplianceReviewed, whose listener e-mails the store owner.
 */
class ComplianceService extends BaseService
{
    public function __construct(private readonly ComplianceProfileRepositoryContract $profiles) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $byStatus = $this->profiles->countsByStatus();
        $counts = [];

        foreach (ComplianceStatus::cases() as $status) {
            $counts[$status->value] = $byStatus[$status->value] ?? 0;
        }

        return $counts;
    }

    public function forTenant(int $tenantId): ?ComplianceProfile
    {
        return $this->profiles->forTenant($tenantId);
    }

    public function decide(ComplianceProfile $profile, ComplianceDecisionData $data): ComplianceProfile
    {
        $note = $data->note !== null ? trim($data->note) : null;

        if ($data->status !== ComplianceStatus::Verified && ($note === null || $note === '')) {
            throw PlatformRuleException::noteRequired();
        }

        if (! $profile->status->canTransitionTo($data->status)) {
            throw PlatformRuleException::invalidTransition($profile->status->label(), $data->status->label());
        }

        $this->transaction(function () use ($profile, $data, $note): void {
            $this->profiles->update($profile, [
                'status' => $data->status,
                'decision_note' => $note === '' ? null : $note,
                'reviewed_by_admin_id' => $data->adminId,
                'reviewed_at' => now(),
            ]);

            if ($data->status === ComplianceStatus::Verified) {
                $this->profiles->updateDocuments($profile, ['status' => ComplianceDocumentStatus::Verified]);
            }

            $this->dispatchAfterCommit(new ComplianceReviewed($profile));
        }, $profile->getConnectionName());

        return $profile->refresh();
    }

    /**
     * Flag one document (verified / needs action) without closing the review.
     */
    public function setDocumentStatus(ComplianceProfile $profile, int $documentId, ComplianceDocumentStatus $status): ComplianceDocument
    {
        $document = $this->profiles->findDocument($profile, $documentId) ?? throw PlatformRuleException::documentMissing();
        $document->fill(['status' => $status])->save();

        return $document;
    }

    /**
     * Risk from the documents: high when a required document is missing or expired,
     * medium when no bank details or a document does not match the owner, else low.
     */
    public function assessRisk(ComplianceProfile $profile): ComplianceRisk
    {
        $documents = $profile->documents;
        $types = $documents->map(fn (ComplianceDocument $document): ComplianceDocumentType => $document->type)->all();

        foreach (ComplianceDocumentType::required() as $required) {
            if (! in_array($required, $types, true)) {
                return ComplianceRisk::High;
            }
        }

        if ($documents->contains(fn (ComplianceDocument $document): bool => $document->isExpired())) {
            return ComplianceRisk::High;
        }

        if ($profile->iban === null || $documents->contains(fn (ComplianceDocument $document): bool => $document->matches_owner === false)) {
            return ComplianceRisk::Medium;
        }

        return ComplianceRisk::Low;
    }

    /**
     * The document of this profile, or a 404 (another store's document is never served).
     */
    public function document(ComplianceProfile $profile, int $documentId): ComplianceDocument
    {
        return $this->profiles->findDocument($profile, $documentId)
            ?? throw (new ModelNotFoundException)->setModel(ComplianceDocument::class, [$documentId]);
    }

    /**
     * The uploaded file still exists on its private disk.
     */
    public function hasFile(ComplianceDocument $document): bool
    {
        return $document->path !== null && Storage::disk($document->disk)->exists($document->path);
    }

    /**
     * File availability per document id (review page previews / empty states).
     *
     * @return array<int, bool>
     */
    public function fileAvailability(ComplianceProfile $profile): array
    {
        return $profile->documents->mapWithKeys(fn (ComplianceDocument $document): array => [$document->id => $this->hasFile($document)])->all();
    }

    /**
     * Streams a document from its private disk (inline preview or download).
     */
    public function stream(ComplianceProfile $profile, int $documentId, bool $download = false): StreamedResponse
    {
        $document = $this->profiles->findDocument($profile, $documentId);

        if ($document === null || $document->path === null || ! Storage::disk($document->disk)->exists($document->path)) {
            throw PlatformRuleException::documentMissing();
        }

        $name = $document->original_name ?? basename($document->path);
        $headers = [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return $download
            ? Storage::disk($document->disk)->download($document->path, $name, $headers)
            : Storage::disk($document->disk)->response($document->path, $name, $headers, 'inline');
    }
}
