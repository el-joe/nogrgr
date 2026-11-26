<?php

namespace App\Modules\Ai\Http\Requests\Vendor;

use App\Modules\Localization\Http\Requests\Vendor\StoreLanguagePurchaseRequest;

/**
 * VEN-074a "Pay & translate": the language picked in the select (one admin USD price
 * per language) + one of the vendor-payment gateways. Whether the language can still
 * be bought is checked by the service.
 */
class AiTranslationPurchaseRequest extends StoreLanguagePurchaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['language_id' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return parent::attributes() + ['language_id' => (string) __('ai::ai_translation.purchase.language')];
    }

    public function languageId(): int
    {
        return (int) $this->validated('language_id');
    }
}
