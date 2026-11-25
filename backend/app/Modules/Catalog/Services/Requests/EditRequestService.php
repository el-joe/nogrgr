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
use App\Modules\Catalog\Repositories\Contracts\EditRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductOverrideRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Product edit requests → tenant overrides.
 *
 *  - submit(): vendor API (VEN-013). Builds the field diff against the central product
 *    (only real changes are kept), stores new images on the private disk.
 *  - approve(): writes the changed fields into the store's TENANT override tables
 *    (product_overrides / variant_overrides, decision #65) inside that tenant's context;
 *    requested images are published to the central public disk first. The central
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
        private readonly ProductOverrideRepositoryContract $overrides,
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
     * Other approved changes of the same store + product (detail sidebar), read from the
     * store's tenant override rows: list of {field, locale, edit_request_id}.
     *
     * @return Collection<int, array{field: string, locale: string|null, edit_request_id: int|null}>
     */
    public function otherOverrides(EditRequest $editRequest): Collection
    {
        $tenant = Tenant::query()->find($editRequest->tenant_id);

        if (! $tenant instanceof Tenant) {
            return collect();
        }

        try {
            $approvals = $this->inTenant($tenant, function () use ($editRequest): array {
                $row = $editRequest->variant_id !== null ? $this->overrides->forVariant($editRequest->variant_id) : $this->overrides->forProduct($editRequest->product_id);

                return (array) ($row?->getAttribute('approvals') ?? []);
            });
        } catch (Throwable) {
            return collect();
        }

        return collect($approvals)
            ->map(static function (mixed $approval, string $key): array {
                [$field, $locale] = array_pad(explode(':', $key, 2), 2, null);

                return ['field' => (string) $field, 'locale' => $locale, 'edit_request_id' => is_array($approval) && isset($approval['edit_request_id']) ? (int) $approval['edit_request_id'] : null];
            })
            ->filter(static fn (array $row): bool => $row['edit_request_id'] !== $editRequest->id)
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
                // Always the central private disk (the vendor submits from tenant context, the admin reviews centrally).
                $store = static fn (): string|false => $image->storeAs('edit-requests/'.$data->tenantId, Str::uuid()->toString().'.'.($image->guessExtension() ?: 'jpg'), self::PRIVATE_DISK);
                $path = tenancy()->initialized ? tenancy()->central($store) : $store();

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

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($editRequest->tenant_id);
        $changes = [];

        foreach ($editRequest->changes as $change) {
            $field = EditableProductField::tryFrom((string) $change['field']);

            if ($field === null) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'locale' => $field->isTranslatable() ? (string) ($change['locale'] ?? '') : null,
                'value' => $field === EditableProductField::Images
                    ? $this->publishImages($editRequest, (array) $change['requested'])
                    : $change['requested'],
                'central' => $change['current'],
            ];
        }

        $this->inTenant($tenant, fn () => $this->writeOverrides($editRequest, $admin, $changes));

        $this->transaction(function () use ($editRequest, $admin): void {
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

    /**
     * Tenant context: per-field writes into product_overrides / variant_overrides.
     *
     * @param  list<array{field: EditableProductField, locale: string|null, value: mixed, central: mixed}>  $changes
     */
    private function writeOverrides(EditRequest $editRequest, Admin $admin, array $changes): void
    {
        $row = $editRequest->variant_id !== null
            ? $this->overrides->variantRow($editRequest->product_id, $editRequest->variant_id)
            : $this->overrides->productRow($editRequest->product_id);
        $approvals = (array) ($row->getAttribute('approvals') ?? []);

        foreach ($changes as $change) {
            $field = $change['field'];
            $column = $editRequest->variant_id !== null && $field === EditableProductField::Name ? 'title' : $field->value;

            if ($field->isTranslatable()) {
                $row->setTranslation($column, (string) $change['locale'], (string) $change['value']);
            } elseif ($field === EditableProductField::Weight) {
                $row->setAttribute($column, max(0, (int) $change['value']));
            } else {
                $row->setAttribute($column, array_values((array) $change['value']));
            }

            $approvals[$field->value.($change['locale'] !== null ? ':'.$change['locale'] : '')] = [
                'edit_request_id' => $editRequest->id,
                'approved_by' => $admin->id,
                'approved_at' => now()->toIso8601String(),
                'central_value' => $change['central'],
            ];
        }

        $row->setAttribute('approvals', $approvals);
        $row->save();
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

            if ($field === null || ! in_array($field, $allowed, true)) {
                continue;
            }

            if ($field->isNumeric()) {
                $value = trim((string) (array_values($locales)[0] ?? ''));
                $current = $variant !== null ? $variant->effectiveWeightGrams() : (int) ($product->weight_grams ?? 0);

                if ($value !== '' && ctype_digit($value) && (int) $value !== $current) {
                    $changes[] = ['field' => $field->value, 'locale' => null, 'current' => $current, 'requested' => (int) $value];
                }

                continue;
            }

            if (! $field->isTranslatable()) {
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

    /**
     * Runs a callback in the store's tenant context and always restores the previous
     * context (also when the callback throws).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function inTenant(Tenant $tenant, Closure $callback): mixed
    {
        $previous = tenant();

        try {
            tenancy()->initialize($tenant);

            return $callback();
        } finally {
            $previous instanceof Tenant ? tenancy()->initialize($previous) : tenancy()->end();
        }
    }

    private function guardPending(EditRequest $editRequest): void
    {
        if (! $editRequest->isPending()) {
            throw RequestRuleException::alreadyReviewed();
        }
    }
}
