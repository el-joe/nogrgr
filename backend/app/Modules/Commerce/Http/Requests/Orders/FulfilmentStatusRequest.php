<?php

namespace App\Modules\Commerce\Http\Requests\Orders;

use App\Modules\Commerce\DTOs\Orders\FulfilmentUpdateData;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shipping status step (ADM-022 / 022a status modal, VEN-021c): status, carrier,
 * tracking number, location / note, the shipping branch and "notify the customer".
 */
class FulfilmentStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(FulfilmentStatus::class)->except([FulfilmentStatus::Pending])],
            'shipping_company_id' => ['nullable', 'integer', Rule::exists(ShippingCompany::class, 'id')->where('is_active', true)],
            'tracking_number' => ['nullable', 'string', 'max:64', 'required_if:status,'.FulfilmentStatus::InTransit->value],
            'location' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:500'],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'notify' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('commerce::fulfilment.fields.status'),
            'shipping_company_id' => __('commerce::fulfilment.fields.company'),
            'tracking_number' => __('commerce::fulfilment.fields.tracking'),
            'location' => __('commerce::fulfilment.fields.location'),
            'note' => __('commerce::fulfilment.fields.note'),
            'branch_id' => __('commerce::fulfilment.fields.branch'),
        ];
    }

    public function toData(ActorType $actor, ?int $actorId): FulfilmentUpdateData
    {
        $string = fn (string $key): ?string => is_string($this->validated($key)) && trim((string) $this->validated($key)) !== '' ? trim((string) $this->validated($key)) : null;
        $int = fn (string $key): ?int => $this->validated($key) !== null ? (int) $this->validated($key) : null;

        return new FulfilmentUpdateData(
            status: FulfilmentStatus::from((string) $this->validated('status')),
            actor: $actor,
            actorId: $actorId,
            shippingCompanyId: $int('shipping_company_id'),
            trackingNumber: $string('tracking_number'),
            location: $string('location'),
            note: $string('note'),
            branchId: $int('branch_id'),
            notifyCustomer: $this->boolean('notify', true),
        );
    }
}
