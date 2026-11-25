<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerProfileData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $phoneCountry,
        public readonly ?string $locale,
    ) {}

    public static function fromRequest(FormRequest $request): static
    {
        $country = $request->filled('phone') ? strtoupper((string) $request->validated('phone_country')) : null;
        $locale = $request->validated('locale');

        return new self(
            name: trim((string) $request->validated('name')),
            email: mb_strtolower(trim((string) $request->validated('email'))),
            phone: $request->filled('phone') ? (string) $request->validated('phone') : null,
            phoneCountry: $country,
            locale: is_string($locale) && $locale !== '' ? $locale : null,
        );
    }
}
