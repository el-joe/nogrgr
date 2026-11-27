<?php

namespace App\Modules\Support\Http\Requests\StoreApi;

use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;

/**
 * The customer's message on their ticket: text and / or up to 5 files.
 */
class CustomerTicketReplyRequest extends CustomerTicketRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => ['nullable', 'string', 'max:5000', 'required_without:files']] + $this->fileRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => __st('support.tickets.fields.message'),
            'files' => __st('support.tickets.fields.files'),
            'files.*' => __st('support.tickets.fields.files'),
        ];
    }

    public function toData(): TicketReplyData
    {
        return new TicketReplyData(
            actor: TicketActor::customer($this->customer()),
            body: $this->filled('body') ? (string) $this->input('body') : null,
            files: $this->uploadedFiles(),
        );
    }
}
