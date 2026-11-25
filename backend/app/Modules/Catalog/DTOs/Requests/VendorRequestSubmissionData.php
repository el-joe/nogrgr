<?php

namespace App\Modules\Catalog\DTOs\Requests;

use App\Modules\Catalog\Enums\RequestKind;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * A new request from the vendor panel (VEN-050b product, 050e manufacturing, 050h brand).
 * `subject` = product title / product name / brand name; `notes` become the description and
 * `files` the attachments of the opening message.
 */
final class VendorRequestSubmissionData extends BaseDTO
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public readonly RequestKind $kind,
        public readonly int $tenantId,
        public readonly string $subject,
        public readonly ?string $notes = null,
        public readonly ?string $url = null,
        public readonly array $countryIds = [],
        public readonly ?int $quantity = null,
        public readonly ?int $productId = null,
        public readonly ?int $categoryId = null,
        public readonly array $files = [],
        public readonly ?string $submittedBy = null,
        public readonly string $senderName = '',
    ) {}
}
