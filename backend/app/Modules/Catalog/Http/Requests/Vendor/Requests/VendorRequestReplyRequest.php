<?php

namespace App\Modules\Catalog\Http\Requests\Vendor\Requests;

use App\Modules\Catalog\DTOs\Requests\RequestReplyData;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestReplyRequest;
use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Store reply in a request conversation: text and/or up to 5 files (10 MB each). The
 * message is signed with the store name (as the platform sees it).
 */
class VendorRequestReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:files'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240', 'mimes:'.RequestReplyRequest::MIMES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => (string) __('catalog::requests.thread.message'),
            'files' => (string) __('catalog::requests.thread.files'),
            'files.*' => (string) __('catalog::requests.thread.file'),
        ];
    }

    public function toData(): RequestReplyData
    {
        /** @var VendorStaff $staff */
        $staff = $this->user('vendor');
        $files = array_values(array_filter((array) $this->file('files', []), static fn (mixed $file): bool => $file instanceof UploadedFile));

        return new RequestReplyData(
            sender: MessageSender::Vendor,
            senderName: (string) (tenant('name') ?? $staff->name),
            body: $this->filled('body') ? (string) $this->input('body') : null,
            files: $files,
        );
    }
}
