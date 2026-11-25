<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Catalog\DTOs\Requests\EditRequestSubmissionData;
use App\Modules\Catalog\Enums\EditableProductField;
use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Events\Requests\EditRequestReviewed;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Models\Requests\ProductOverride;
use App\Modules\Catalog\Repositories\Contracts\EditRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Identity\Models\Admin;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Product edit requests → tenant overrides.
 *
 *  - submit(): vendor API (VEN-013). Builds the field diff against the central product
 *    (only real changes are kept), stores new images on the private disk.
 *  - approve(): writes one catalog_product_overrides row per changed field / locale for
 *    that tenant only; requested images are published to the public disk. The central
 *    product is never modified.
 *  - reject(): requires a reason, shown to the vendor.
 */
class EditRequestService extends BaseService
{
    public const PRIVATE_DISK = 'local';

    public const PUBLIC_DISK = 'public';

    public function __construct(
        private readonly EditRequestRepositoryContract $editRequests,
        private readonly ProductRepositoryContract $products,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<EditRequest>
     */
    public function listing(array $filters = []): Builder
    {
        return $this->editRequests->listing($filters);
    }

    public function pendingCount(): int
    {
        return $this->editRequests->pendingCount();
    }

    /**
     * Other approved overrides of the same store + product (detail sidebar).
     *
     * @return Collection<int, ProductOverride>
     */
    public function otherOverrides(EditRequest $editRequest): Collection
    {
        return $this->editRequests->overridesFor($editRequest->tenant_id, $editRequest->product_id)
            ->filter(static fn (ProductOverride $override): bool => $override->edit_request_id !== $editRequest->id)
            ->values();
    }

    public function submit(EditRequestSubmissionData $data): EditRequest
    {
        /** @var Product $product */
        $product = $this->products->query()->findOrFail($data->productId);
        $variant = $data->variantId !== null ? $product->variants()->findOrFail($data->variantId) : null;

        if ($this->editRequests->pendingFor($data->tenantId, $product->id, $variant?->id) !== null) {
            throw RequestRuleException::pendingExists();
        }

        $changes = $this->diff($product, $variant, $data->values);
        $files = [];

        if ($data->images !== []) {
            foreach ($data->images as $image) {
                $path = $image->storeAs('edit-requests/'.$data->tenantId, Str::uuid()->toString().'.'.($image->guessExtension() ?: 'jpg'), self::PRIVATE_DISK);

                if ($path !== false) {
                    $files[] = ['path' => $path, 'name' => Str::limit($image->getClientOriginalName(), 180, ''), 'size' => (int) $image->getSize(), 'mime_type' => $image->getMimeType()];
                }
            }

            $changes[] = [
                'field' => EditableProductField::Images->value,
                'locale' => null,
                'current' => $this->currentImages($product, $variant),
                'requested' => array_column($files, 'path'),
            ];
        }

        if ($changes === []) {
            throw RequestRuleException::noChanges();
        }

        /** @var EditRequest $editRequest */
        $editRequest = $this->editRequests->create([
            'tenant_id' => $data->tenantId,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'changes' => $changes,
            'files' => $files === [] ? null : $files,
            'vendor_note' => $data->vendorNote,
            'submitted_by' => $data->submittedBy,
            'status' => EditRequestStatus::Pending,
        ]);

        return $editRequest;
    }

    public function approve(EditRequest $editRequest, Admin $admin): EditRequest
    {
        $this->guardPending($editRequest);

        $this->transaction(function () use ($editRequest, $admin): void {
            foreach ($editRequest->changes as $change) {
                $field = EditableProductField::tryFrom((string) $change['field']);

                if ($field === null) {
                    continue;
                }

                $requested = $field === EditableProductField::Images
                    ? json_encode($this->publishImages($editRequest, (array) $change['requested']), JSON_THROW_ON_ERROR)
                    : (is_scalar($change['requested']) ? (string) $change['requested'] : null);
                $current = is_array($change['current']) ? json_encode($change['current'], JSON_THROW_ON_ERROR) : (is_scalar($change['current']) ? (string) $change['current'] : null);

                $this->editRequests->upsertOverride($editRequest->tenant_id, $editRequest->product_id, $editRequest->variant_id, $field->value, (string) ($change['locale'] ?? ''), [
                    'value' => $requested,
                    'central_value' => $current,
                    'edit_request_id' => $editRequest->id,
                    'approved_by' => $admin->id,
                ]);
            }

            $this->editRequests->update($editRequest, [
                'status' => EditRequestStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'reason' => null,
            ]);
            $this->dispatchAfterCommit(new EditRequestReviewed($editRequest));
        }, $editRequest->getConnectionName());

        return $editRequest;
    }

    public function reject(EditRequest $editRequest, Admin $admin, string $reason): EditRequest
    {
        $this->guardPending($editRequest);
        $reason = trim($reason);

        if ($reason === '') {
            throw RequestRuleException::emptyMessage();
        }

        $this->editRequests->update($editRequest, [
            'status' => EditRequestStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'reason' => $reason,
        ]);
        event(new EditRequestReviewed($editRequest));

        return $editRequest;
    }

    /**
     * Streams one of the request's new images from the private disk (admin preview).
     */
    public function streamFile(EditRequest $editRequest, int $index): StreamedResponse
    {
        $file = $editRequest->files[$index] ?? null;

        if ($file === null || ! Storage::disk(self::PRIVATE_DISK)->exists($file['path'])) {
            throw new NotFoundHttpException((string) __('catalog::requests.errors.attachment_missing'));
        }

        return Storage::disk(self::PRIVATE_DISK)->response($file['path'], $file['name'], [
            'Content-Type' => $file['mime_type'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * Public URL of a current (central) image path.
     */
    public function publicUrl(string $path): string
    {
        return Storage::disk(self::PUBLIC_DISK)->url($path);
    }

    /**
     * @param  array<string, array<string, string|null>>  $values
     * @return list<array{field: string, locale: string|null, current: mixed, requested: mixed}>
     */
    private function diff(Product $product, ?ProductVariant $variant, array $values): array
    {
        $allowed = $variant !== null ? EditableProductField::forVariant() : EditableProductField::cases();
        $changes = [];

        foreach ($values as $fieldName => $locales) {
            $field = EditableProductField::tryFrom((string) $fieldName);

            if ($field === null || ! $field->isTranslatable() || ! in_array($field, $allowed, true)) {
                continue;
            }

            foreach ($locales as $locale => $value) {
                $value = $value !== null ? trim($value) : '';
                $current = $variant !== null
                    ? (string) $variant->getTranslation('title', (string) $locale, false)
                    : (string) $product->getTranslation($field->value, (string) $locale, false);

                if ($value === '' || $value === $current) {
                    continue;
                }

                $changes[] = ['field' => $field->value, 'locale' => (string) $locale, 'current' => $current, 'requested' => $value];
            }
        }

        return $changes;
    }

    /**
     * @return list<string>
     */
    private function currentImages(Product $product, ?ProductVariant $variant): array
    {
        if ($variant !== null) {
            return $variant->image_path !== null ? [$variant->image_path] : [];
        }

        return $product->media()->orderBy('sort_order')->get()->map(static fn (CatalogMedia $media): string => $media->path)->values()->all();
    }

    /**
     * Copies approved images from the private disk to the public disk (store-specific folder).
     *
     * @param  array<int, mixed>  $paths
     * @return list<string>
     */
    private function publishImages(EditRequest $editRequest, array $paths): array
    {
        $published = [];

        foreach ($paths as $path) {
            if (! is_string($path) || ! Storage::disk(self::PRIVATE_DISK)->exists($path)) {
                continue;
            }

            $target = 'catalog/overrides/'.$editRequest->tenant_id.'/'.$editRequest->product_id.'/'.basename($path);
            Storage::disk(self::PUBLIC_DISK)->put($target, (string) Storage::disk(self::PRIVATE_DISK)->get($path));
            $published[] = $target;
        }

        return $published;
    }

    private function guardPending(EditRequest $editRequest): void
    {
        if (! $editRequest->isPending()) {
            throw RequestRuleException::alreadyReviewed();
        }
    }
}
