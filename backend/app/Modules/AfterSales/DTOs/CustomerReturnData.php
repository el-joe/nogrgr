<?php

namespace App\Modules\AfterSales\DTOs;

use App\Modules\AfterSales\Enums\ReturnMethod;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * A return requested by the customer (SF-ECO-030 / APP-016): one reason and description
 * for several order lines (one ReturnRequest per line), with photo / video evidence.
 */
final class CustomerReturnData extends BaseDTO
{
    /**
     * @param  list<ReturnLineData>  $lines
     * @param  list<UploadedFile>  $photos
     */
    public function __construct(
        public readonly string $reasonCode,
        public readonly string $description,
        public readonly ReturnType $type,
        public readonly array $lines,
        public readonly array $photos = [],
        public readonly ?UploadedFile $video = null,
        public readonly ?ReturnMethod $returnMethod = null,
        public readonly ?string $customerNote = null,
    ) {}
}
