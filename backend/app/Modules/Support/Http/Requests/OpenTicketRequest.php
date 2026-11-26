<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Support\DTOs\OpenTicketData;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * New support ticket (VEN-091a, Figma 88:113398): subject, category, priority, message
 * and up to 5 attachments (PDF, images, office files, ZIP — 10 MB each).
 */
class OpenTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.TicketReplyRequest::MAX_FILES],
            'files.*' => ['file', 'max:'.TicketReplyRequest::MAX_KB, 'mimes:'.TicketReplyRequest::MIMES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject' => (string) __('support::tickets.fields.subject'),
            'category' => (string) __('support::tickets.fields.category'),
            'priority' => (string) __('support::tickets.fields.priority'),
            'body' => (string) __('support::tickets.fields.message'),
            'files' => (string) __('support::tickets.fields.files'),
            'files.*' => (string) __('support::tickets.fields.file'),
        ];
    }

    public function toData(TicketActor $actor, ?int $tenantId, ?int $ownerId = null): OpenTicketData
    {
        return new OpenTicketData(
            actor: $actor,
            tenantId: $tenantId,
            ownerId: $ownerId,
            subject: (string) $this->validated('subject'),
            category: TicketCategory::from((string) $this->validated('category')),
            priority: TicketPriority::from((string) $this->validated('priority')),
            body: (string) $this->validated('body'),
            files: array_values(array_filter((array) $this->file('files', []), static fn (mixed $file): bool => $file instanceof UploadedFile)),
        );
    }
}
