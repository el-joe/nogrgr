<?php

namespace App\Modules\AfterSales\Http\Requests\StoreApi;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Commerce\Exceptions\CommerceException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Store API input of a signed-in customer: a guest gets the 401 JSON before any
 * validation runs.
 */
abstract class StoreApiCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return StoreApiCustomer::resolve($this) !== null;
    }

    protected function failedAuthorization(): void
    {
        throw CommerceException::blocked(CheckoutBlock::SignInRequired);
    }

    /**
     * @return list<UploadedFile>
     */
    protected function uploadedPhotos(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), static fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    protected function uploadedVideo(): ?UploadedFile
    {
        $video = $this->file('video');

        return $video instanceof UploadedFile ? $video : null;
    }
}
