<?php

namespace App\Modules\AfterSales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Retry / mark completed / reject modal (ADM-024 / VEN-023): a note, required to reject.
 */
class RefundActionRequest extends FormRequest
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
            'action' => ['required', Rule::in(['retry', 'complete', 'reject'])],
            'note' => [$this->input('action') === 'reject' ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('after-sales::refunds.fields.note'), 'action' => __('after-sales::refunds.fields.action')];
    }

    public function action(): string
    {
        return (string) $this->validated('action');
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
