<?php

namespace App\Modules\Support\Http\Requests\StoreApi;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Identity\Models\Customer;
use App\Modules\Support\Http\Requests\TicketReplyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Store API input of a signed-in customer on their support tickets: a guest gets the 401
 * JSON before any validation runs.
 */
abstract class CustomerTicketRequest extends FormRequest
{
    public const MIMES = TicketReplyRequest::MIMES;

    public function authorize(): bool
    {
        return StoreApiCustomer::resolve($this) !== null;
    }

    public function customer(): Customer
    {
        return StoreApiCustomer::resolve($this) ?? throw CommerceException::blocked(CheckoutBlock::SignInRequired);
    }

    protected function failedAuthorization(): void
    {
        throw CommerceException::blocked(CheckoutBlock::SignInRequired);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fileRules(): array
    {
        return [
            'files' => ['nullable', 'array', 'max:'.TicketReplyRequest::MAX_FILES],
            'files.*' => ['file', 'max:'.TicketReplyRequest::MAX_KB, 'mimes:'.self::MIMES],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    protected function uploadedFiles(): array
    {
        return array_values(array_filter((array) $this->file('files', []), static fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
