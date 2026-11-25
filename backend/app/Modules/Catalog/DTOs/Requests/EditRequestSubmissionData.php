<?php

namespace App\Modules\Catalog\DTOs\Requests;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Vendor submission of a product edit request (VEN-013 calls EditRequestService::submit()).
 * `values`: field => locale => requested text for translatable fields
 * (e.g. ['name' => ['ar' => '…'], 'summary' => ['en' => '…']]); `images`: new image files
 * that replace the gallery for this store.
 */
final class EditRequestSubmissionData extends BaseDTO
{
    /**
     * @param  array<string, array<string, string|null>>  $values
     * @param  list<UploadedFile>  $images
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $productId,
        public readonly ?int $variantId,
        public readonly array $values,
        public readonly array $images = [],
        public readonly ?string $vendorNote = null,
        public readonly ?string $submittedBy = null,
    ) {}
}
