<?php

namespace App\Modules\Commerce\Http\Requests\Orders;

use App\Modules\Commerce\Enums\AttachmentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Fulfilment file upload (ADM-022a): PDF / image up to 10 MB with its kind.
 */
class AttachmentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'kind' => ['required', Rule::enum(AttachmentKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => __('commerce::fulfilment.attachments.file'), 'kind' => __('commerce::fulfilment.attachments.kind')];
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function kind(): AttachmentKind
    {
        return AttachmentKind::from((string) $this->validated('kind'));
    }
}
