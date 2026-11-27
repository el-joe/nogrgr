<?php

namespace App\Modules\Support\Http\Requests\StoreApi;

use App\Modules\Engagement\Enums\SupportSubject;
use App\Modules\Support\DTOs\OpenCustomerTicketData;
use Illuminate\Validation\Rule;

/**
 * New customer ticket: topic, subject, message, optional own order (checked by the
 * service), up to 5 files of 10 MB.
 */
class OpenCustomerTicketRequest extends CustomerTicketRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', Rule::enum(SupportSubject::class)],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
            'order_uuid' => ['nullable', 'uuid'],
        ] + $this->fileRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'topic' => __st('support.tickets.fields.topic'),
            'subject' => __st('support.tickets.fields.subject'),
            'body' => __st('support.tickets.fields.message'),
            'order_uuid' => __st('support.tickets.fields.order'),
            'files' => __st('support.tickets.fields.files'),
            'files.*' => __st('support.tickets.fields.files'),
        ];
    }

    public function toData(): OpenCustomerTicketData
    {
        return new OpenCustomerTicketData(
            topic: SupportSubject::from((string) $this->validated('topic')),
            subject: (string) $this->validated('subject'),
            body: (string) $this->validated('body'),
            orderUuid: $this->filled('order_uuid') ? (string) $this->validated('order_uuid') : null,
            files: $this->uploadedFiles(),
        );
    }
}
