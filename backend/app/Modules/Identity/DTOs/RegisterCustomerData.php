<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterCustomerData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $phone = null,
        public readonly ?string $phoneCountry = null,
        public readonly bool $remember = false,
    ) {}

    public static function fromRequest(FormRequest $request): static
    {
        $country = $request->filled('phone') ? strtoupper((string) $request->validated('phone_country')) : null;

        return new self(
            name: trim((string) $request->validated('name')),
            email: mb_strtolower(trim((string) $request->validated('email'))),
            password: (string) $request->validated('password'),
            phone: $request->filled('phone') ? (string) $request->validated('phone') : null,
            phoneCountry: $country,
            remember: $request->boolean('remember'),
        );
    }
}
