<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Reply on a ticket (any surface): text and/or up to 5 files of 10 MB; `internal` (admin
 * note) is honoured only for admins by the controller.
 */
class TicketReplyRequest extends FormRequest
{
    public const MIMES = 'jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip';

    public const MAX_FILES = 5;

    public const MAX_KB = 10240;

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
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:'.self::MAX_KB, 'mimes:'.self::MIMES],
            'internal' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => (string) __('support::tickets.fields.message'),
            'files' => (string) __('support::tickets.fields.files'),
            'files.*' => (string) __('support::tickets.fields.file'),
        ];
    }

    public function toData(TicketActor $actor, bool $allowInternal = false): TicketReplyData
    {
        return new TicketReplyData(
            actor: $actor,
            body: $this->filled('body') ? (string) $this->input('body') : null,
            files: $this->uploadedFiles(),
            internal: $allowInternal && $this->boolean('internal'),
        );
    }

    /**
     * @return list<UploadedFile>
     */
    protected function uploadedFiles(): array
    {
        return array_values(array_filter((array) $this->file('files', []), static fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
