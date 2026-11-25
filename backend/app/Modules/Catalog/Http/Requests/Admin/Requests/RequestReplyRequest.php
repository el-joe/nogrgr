<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use App\Modules\Catalog\DTOs\Requests\RequestReplyData;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Identity\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Admin reply in a request conversation: text and/or up to 5 files (10 MB each).
 */
class RequestReplyRequest extends FormRequest
{
    public const MIMES = 'jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip';

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
            'files.*' => ['file', 'max:10240', 'mimes:'.self::MIMES],
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
        /** @var Admin $admin */
        $admin = $this->user('admin');
        $files = array_values(array_filter((array) $this->file('files', []), static fn (mixed $file): bool => $file instanceof UploadedFile));

        return new RequestReplyData(
            sender: MessageSender::Admin,
            senderName: (string) $admin->name,
            body: $this->filled('body') ? (string) $this->input('body') : null,
            files: $files,
            adminId: (int) $admin->id,
        );
    }
}
