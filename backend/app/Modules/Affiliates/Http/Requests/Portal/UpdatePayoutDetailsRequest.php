<?php

namespace App\Modules\Affiliates\Http\Requests\Portal;

use App\Modules\Affiliates\DTOs\PayoutDetailsData;
use App\Modules\Affiliates\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AFF-013a payout details: PayPal (e-mail) or bank transfer (bank, account holder, IBAN).
 */
class UpdatePayoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('bank_iban'))) {
            $this->merge(['bank_iban' => strtoupper((string) preg_replace('/\s+/', '', $this->input('bank_iban')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payout_method' => ['required', Rule::in([PayoutMethod::Paypal->value, PayoutMethod::BankTransfer->value])],
            'paypal_email' => ['required_if:payout_method,paypal', 'nullable', 'string', 'email:rfc', 'max:191'],
            'bank_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:120'],
            'bank_account_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:120'],
            'bank_iban' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payout_method' => (string) __('affiliates::panel.profile.method'),
            'paypal_email' => (string) __('affiliates::panel.profile.paypal_email'),
            'bank_name' => (string) __('affiliates::panel.profile.bank_name'),
            'bank_account_name' => (string) __('affiliates::panel.profile.bank_account_name'),
            'bank_iban' => (string) __('affiliates::panel.profile.bank_iban'),
        ];
    }

    public function toData(): PayoutDetailsData
    {
        return new PayoutDetailsData(
            method: PayoutMethod::from((string) $this->validated('payout_method')),
            paypalEmail: $this->validated('paypal_email'),
            bankName: $this->validated('bank_name'),
            bankAccountName: $this->validated('bank_account_name'),
            bankIban: $this->validated('bank_iban'),
        );
    }
}
