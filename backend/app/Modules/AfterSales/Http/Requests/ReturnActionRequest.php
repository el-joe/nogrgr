<?php

namespace App\Modules\AfterSales\Http\Requests;

use App\Modules\AfterSales\DTOs\ReturnActionData;
use App\Modules\AfterSales\Enums\InspectionResult;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\Inventory\Models\Branch;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * One return transition modal (ADM-023a / VEN-022a): the action comes from the URL;
 * reject / ask-info need a note, inspect a result, ship-exchange a tracking number.
 */
class ReturnActionRequest extends FormRequest
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
        $action = $this->action();

        return [
            'note' => [in_array($action, [ReturnAction::Reject, ReturnAction::RequestInfo], true) ? 'required' : 'nullable', 'string', 'max:2000'],
            'result' => [$action === ReturnAction::Inspect ? 'required' : 'nullable', Rule::enum(InspectionResult::class)],
            'condition' => ['nullable', 'string', 'max:120'],
            'restock' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'amount' => [$action === ReturnAction::Refund ? 'required' : 'nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'then_refund' => ['nullable', 'boolean'],
            'tracking_number' => [$action === ReturnAction::ShipExchange ? 'required' : 'nullable', 'string', 'max:64'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['file', 'image', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => __('after-sales::returns.fields.note'),
            'result' => __('after-sales::returns.fields.result'),
            'condition' => __('after-sales::returns.fields.condition'),
            'branch_id' => __('after-sales::returns.fields.branch'),
            'amount' => __('after-sales::refunds.fields.amount'),
            'tracking_number' => __('after-sales::returns.fields.tracking'),
            'photos.*' => __('after-sales::returns.fields.photos'),
        ];
    }

    public function action(): ReturnAction
    {
        return ReturnAction::tryFrom((string) $this->route('action')) ?? abort(404);
    }

    public function toData(): ReturnActionData
    {
        $note = $this->validated('note');
        $amount = $this->validated('amount');
        /** @var list<UploadedFile> $photos */
        $photos = array_values(array_filter((array) $this->file('photos', []), static fn (mixed $file): bool => $file instanceof UploadedFile));

        return new ReturnActionData(
            action: $this->action(),
            note: is_string($note) && trim($note) !== '' ? trim($note) : null,
            result: InspectionResult::tryFrom((string) $this->validated('result')),
            condition: is_string($this->validated('condition')) ? trim((string) $this->validated('condition')) : null,
            restock: $this->boolean('restock'),
            branchId: $this->validated('branch_id') !== null ? (int) $this->validated('branch_id') : null,
            amount: $amount !== null ? Money::of((string) $amount) : null,
            thenRefund: $this->boolean('then_refund'),
            trackingNumber: is_string($this->validated('tracking_number')) ? trim((string) $this->validated('tracking_number')) : null,
            photos: $photos,
        );
    }
}
