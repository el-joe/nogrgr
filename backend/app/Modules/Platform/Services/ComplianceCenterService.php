<?php

namespace App\Modules\Platform\Services;

use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CityRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Platform\DTOs\ComplianceCenterData;
use App\Modules\Platform\Enums\ComplianceAccountType;
use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceRisk;
use App\Modules\Platform\Enums\ComplianceSection;
use App\Modules\Platform\Enums\ComplianceSectionState;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Events\ComplianceSubmitted;
use App\Modules\Platform\Exceptions\ComplianceCenterException;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Vendor › Settings › Compliance center (VEN-084). The store fills the same central KYC
 * profile the admin reviews (ComplianceService, ADM-044): identity, company, bank, document
 * files (private `central` disk, one file per type) and the marketplace terms. Always pinned
 * to the given tenant id on the central connection.
 *
 * Lifecycle: draft → submit (all required data + current terms) → needs_review (admins
 * notified through ComplianceSubmitted) → verified | needs_action | rejected; the vendor can
 * edit and re-submit from needs_action / rejected, never while in review or verified.
 */
class ComplianceCenterService extends BaseService
{
    public const DISK = 'central';

    /**
     * Upload limit per file, in kilobytes (Figma: "max 10 MB").
     */
    public const MAX_FILE_KB = 10240;

    /**
     * @var list<string>
     */
    public const FILE_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'zip'];

    public function __construct(
        private readonly ComplianceProfileRepositoryContract $profiles,
        private readonly ComplianceService $compliance,
        private readonly CountryRepositoryContract $countries,
        private readonly CityRepositoryContract $cities,
        private readonly StoreCountries $storeCountries,
        private readonly PageRepositoryContract $pages,
    ) {}

    public function profile(int $tenantId): ?ComplianceProfile
    {
        return $this->profiles->forTenant($tenantId)?->loadMissing(['documents', 'country', 'city']);
    }

    /**
     * Saves the form as it is (draft); a submitted profile can only change in needs_action / rejected.
     */
    public function save(int $tenantId, ComplianceCenterData $data, ?string $ip = null): ComplianceProfile
    {
        $existing = $this->profiles->forTenant($tenantId);

        if ($existing !== null && ! $existing->status->isEditableByVendor()) {
            throw ComplianceCenterException::locked();
        }

        $attributes = $data->profileAttributes();

        if ($data->acceptTerms) {
            $attributes += [
                'terms_version' => $this->termsVersion(),
                'terms_accepted_at' => now(),
                'terms_accepted_ip' => $ip,
            ];
        }

        $stored = [];
        $replaced = [];

        try {
            $profile = $this->transaction(function () use ($existing, $tenantId, $attributes, $data, &$stored, &$replaced): ComplianceProfile {
                if ($existing === null) {
                    /** @var ComplianceProfile $profile */
                    $profile = $this->profiles->create(['tenant_id' => $tenantId, 'status' => ComplianceStatus::Draft, 'risk' => ComplianceRisk::Low] + $attributes);
                } else {
                    /** @var ComplianceProfile $profile */
                    $profile = $this->profiles->update($existing, $attributes);
                }

                foreach (ComplianceDocumentType::cases() as $type) {
                    $file = $data->file($type);

                    if ($file !== null) {
                        $this->storeDocument($profile, $type, $file, $stored, $replaced);
                    }
                }

                $this->syncDocumentDetails($profile);

                return $profile;
            }, $this->connection());
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk(self::DISK)->delete($path);
            }

            throw $exception;
        }

        foreach ($replaced as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $profile->refresh()->load(['documents', 'country', 'city']);
    }

    /**
     * Saves, checks that every required field / document and the current terms are there
     * (field errors otherwise) and sends the profile for review.
     */
    public function submit(int $tenantId, ComplianceCenterData $data, ?string $ip = null): ComplianceProfile
    {
        $profile = $this->save($tenantId, $data, $ip);
        $missing = array_merge(...array_values($this->missing($profile)));

        if ($missing !== []) {
            throw ValidationException::withMessages(array_map(static fn (string $message): array => [$message], $missing));
        }

        if (! $profile->status->canTransitionTo(ComplianceStatus::NeedsReview)) {
            throw ComplianceCenterException::locked();
        }

        $resubmitted = $profile->submitted_at !== null;

        $this->transaction(function () use ($profile, $resubmitted): void {
            $this->profiles->update($profile, [
                'status' => ComplianceStatus::NeedsReview,
                'submitted_at' => now(),
                'risk' => $this->compliance->assessRisk($profile),
            ]);

            $this->dispatchAfterCommit(new ComplianceSubmitted($profile, $resubmitted));
        }, $this->connection());

        return $profile->refresh()->load(['documents', 'country', 'city']);
    }

    /**
     * Removes one uploaded document (file + row) while the profile is editable.
     */
    public function removeDocument(int $tenantId, int $documentId): ComplianceDocumentType
    {
        $profile = $this->requireProfile($tenantId);
        $document = $this->compliance->document($profile, $documentId);

        if (! $profile->status->isEditableByVendor()) {
            throw ComplianceCenterException::documentNotRemovable();
        }

        $disk = $document->disk;
        $path = $document->path;
        $type = $document->type;
        $this->profiles->deleteDocument($document);

        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }

        return $type;
    }

    /**
     * Streams an own document from the private disk (404 for another store's document).
     */
    public function stream(int $tenantId, int $documentId, bool $download = false): StreamedResponse
    {
        $profile = $this->requireProfile($tenantId);
        $document = $this->compliance->document($profile, $documentId);

        abort_unless($this->compliance->hasFile($document), 404);

        return $this->compliance->stream($profile, $document->id, $download);
    }

    /**
     * Missing required data per step: section value => [form field key => message].
     *
     * @return array<string, array<string, string>>
     */
    public function missing(?ComplianceProfile $profile): array
    {
        $types = $profile?->documents->map(fn (ComplianceDocument $document): string => $document->type->value)->all() ?? [];
        $missing = [];
        $require = function (ComplianceSection $section, string $field, mixed $value) use (&$missing): void {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[$section->value][$field] = __('validation.required', ['attribute' => __('platform::vendor_compliance.fields.'.$field)]);
            }
        };
        $requireDocument = function (ComplianceSection $section, ComplianceDocumentType $type) use (&$missing, $types): void {
            if (! in_array($type->value, $types, true)) {
                $missing[$section->value]['documents.'.$type->value] = __('platform::vendor_compliance.errors.document_required', ['document' => $type->label()]);
            }
        };

        $require(ComplianceSection::Identity, 'owner_name', $profile?->owner_name);
        $require(ComplianceSection::Identity, 'owner_id_number', $profile?->owner_id_number);
        $require(ComplianceSection::Identity, 'owner_phone', $profile?->owner_phone);
        $requireDocument(ComplianceSection::Identity, ComplianceDocumentType::NationalId);

        $require(ComplianceSection::Company, 'account_type', $profile?->account_type?->value);

        if ($profile?->account_type === ComplianceAccountType::Company) {
            $require(ComplianceSection::Company, 'legal_name', $profile->legal_name);
            $require(ComplianceSection::Company, 'registration_number', $profile->registration_number);
            $requireDocument(ComplianceSection::Company, ComplianceDocumentType::CommercialRegistration);
        }

        $require(ComplianceSection::Company, 'country_id', $profile?->country_id);
        $require(ComplianceSection::Company, 'address', $profile?->address);
        $require(ComplianceSection::Company, 'company_email', $profile?->company_email);

        $require(ComplianceSection::Bank, 'bank_name', $profile?->bank_name);
        $require(ComplianceSection::Bank, 'iban', $profile?->iban);
        $require(ComplianceSection::Bank, 'account_name', $profile?->account_name);

        if (! $this->termsAccepted($profile)) {
            $missing[ComplianceSection::Terms->value]['accept_terms'] = __('platform::vendor_compliance.errors.terms_required');
        }

        return $missing;
    }

    /**
     * Stepper / Status card rows in step order.
     *
     * @return array<string, ComplianceSectionState>
     */
    public function sections(?ComplianceProfile $profile): array
    {
        $missing = $this->missing($profile);
        $states = [];

        foreach (ComplianceSection::cases() as $section) {
            $states[$section->value] = $this->sectionState($section, $profile, $missing[$section->value] ?? []);
        }

        return $states;
    }

    /**
     * Version of the marketplace terms: the date the central "terms" page last changed,
     * so an edited page asks every store to accept again.
     */
    public function termsVersion(): string
    {
        $page = $this->pages->findPublicBySlug('terms');

        return $page?->updated_at !== null ? $page->updated_at->format('Y.m.d') : '1.0';
    }

    public function termsUrl(): ?string
    {
        return Route::has('central.terms') ? route('central.terms') : null;
    }

    public function termsAccepted(?ComplianceProfile $profile): bool
    {
        return $profile?->terms_accepted_at !== null && $profile->terms_version === $this->termsVersion();
    }

    /**
     * Active central countries (registration country of the store), id => "flag name".
     *
     * @return array<int, string>
     */
    public function countryOptions(): array
    {
        $options = [];

        foreach ($this->countries->active() as $country) {
            /** @var Country $country */
            $options[(int) $country->id] = trim($country->flag.' '.$country->name);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public function isActiveCountry(int $countryId): bool
    {
        return $this->countries->findActive($countryId) !== null;
    }

    /**
     * Select2 city options of an active country.
     *
     * @return list<array{id: string, text: string}>
     */
    public function cityOptions(int $countryId, ?string $term = null): array
    {
        abort_unless($this->isActiveCountry($countryId), 404);

        return $this->storeCountries->citySuggestions($countryId, $term, 50)
            ->map(static fn (City $city): array => ['id' => (string) $city->id, 'text' => (string) $city->name])
            ->values()
            ->all();
    }

    public function cityBelongsToCountry(int $cityId, int $countryId): bool
    {
        return $this->cities->activeForCountry($countryId)->contains('id', $cityId);
    }

    /**
     * Whether each document's file is still on its disk (download link vs "missing" state).
     *
     * @return array<int, bool>
     */
    public function fileAvailability(?ComplianceProfile $profile): array
    {
        return $profile !== null ? $this->compliance->fileAvailability($profile) : [];
    }

    /**
     * @param  array<string, string>  $missing
     */
    private function sectionState(ComplianceSection $section, ?ComplianceProfile $profile, array $missing): ComplianceSectionState
    {
        if ($section === ComplianceSection::Terms) {
            if ($missing === []) {
                return ComplianceSectionState::Accepted;
            }

            return $profile?->terms_accepted_at !== null ? ComplianceSectionState::NeedsAction : ComplianceSectionState::Incomplete;
        }

        if ($missing !== [] || $profile === null) {
            return ComplianceSectionState::Incomplete;
        }

        if ($profile->status === ComplianceStatus::Verified) {
            return ComplianceSectionState::Verified;
        }

        $documents = $profile->documents->filter(fn (ComplianceDocument $document): bool => in_array($document->type, $section->documentTypes(), true));

        if ($documents->contains(fn (ComplianceDocument $document): bool => $document->status === ComplianceDocumentStatus::NeedsAction)) {
            return ComplianceSectionState::NeedsAction;
        }

        if ($documents->isNotEmpty() && $documents->every(fn (ComplianceDocument $document): bool => $document->status === ComplianceDocumentStatus::Verified)) {
            return ComplianceSectionState::Verified;
        }

        return $profile->status === ComplianceStatus::NeedsReview ? ComplianceSectionState::InReview : ComplianceSectionState::Complete;
    }

    /**
     * @param  list<string>  $stored
     * @param  list<array{0: string, 1: string}>  $replaced
     */
    private function storeDocument(ComplianceProfile $profile, ComplianceDocumentType $type, UploadedFile $file, array &$stored, array &$replaced): void
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $path = $file->storeAs('compliance/'.$profile->tenant_id, Str::uuid()->toString().'.'.$extension, self::DISK);

        if ($path === false) {
            throw ComplianceCenterException::uploadFailed();
        }

        $stored[] = $path;
        $attributes = [
            'type' => $type,
            'status' => ComplianceDocumentStatus::Review,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'matches_owner' => null,
        ];
        $existing = $this->profiles->documentOfType($profile, $type);

        if ($existing === null) {
            $this->profiles->createDocument($profile, $attributes);

            return;
        }

        if ($existing->path !== null) {
            $replaced[] = [$existing->disk, $existing->path];
        }

        $this->profiles->updateDocument($existing, $attributes);
    }

    /**
     * Mirrors the form's numbers / names / expiry dates onto the matching document rows,
     * so the admin review cards show them next to the file.
     */
    private function syncDocumentDetails(ComplianceProfile $profile): void
    {
        $details = [
            ComplianceDocumentType::NationalId->value => [
                'document_number' => $profile->owner_id_number,
                'holder_name' => $profile->owner_name,
                'expires_at' => $profile->owner_id_expires_at,
            ],
            ComplianceDocumentType::CommercialRegistration->value => [
                'document_number' => $profile->registration_number,
                'holder_name' => $profile->legal_name,
                'expires_at' => $profile->registration_expires_at,
            ],
            ComplianceDocumentType::TaxCertificate->value => [
                'document_number' => $profile->vat_number,
                'holder_name' => $profile->legal_name,
            ],
            ComplianceDocumentType::BankLetter->value => [
                'holder_name' => $profile->account_name,
            ],
        ];

        foreach ($details as $type => $attributes) {
            $document = $this->profiles->documentOfType($profile, ComplianceDocumentType::from($type));

            if ($document !== null) {
                $this->profiles->updateDocument($document, $attributes);
            }
        }
    }

    private function requireProfile(int $tenantId): ComplianceProfile
    {
        $profile = $this->profiles->forTenant($tenantId);
        abort_if($profile === null, 404);

        return $profile;
    }

    private function connection(): string
    {
        return (string) config('tenancy.database.central_connection');
    }
}
