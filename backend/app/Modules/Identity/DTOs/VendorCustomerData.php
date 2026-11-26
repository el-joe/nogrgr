<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vendor › Customers create / edit (VEN-025a, Figma 84:107236) after CustomerRequest:
 * the full name is "first last", `phone` is E.164 with its ISO2 country, empty optional
 * strings are null; `newsletter` = subscribe the e-mail to the store newsletter.
 */
final class VendorCustomerData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $phoneCountry,
        public readonly ?string $locale,
        public readonly bool $newsletter,
    ) {}

    public static function fromRequest(FormRequest $request): static
    {
        $optional = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : null;

            return $value === '' ? null : $value;
        };
        $phone = $optional($request->validated('phone'));
        $email = $optional($request->validated('email'));

        return new self(
            name: trim(trim((string) $request->validated('first_name')).' '.trim((string) $request->validated('last_name'))),
            email: $email !== null ? mb_strtolower($email) : null,
            phone: $phone,
            phoneCountry: $phone !== null ? strtoupper((string) $request->validated('phone_country')) : null,
            locale: $optional($request->validated('locale')),
            newsletter: $request->boolean('newsletter'),
        );
    }

    /**
     * Columns of `customers` set from the form.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country' => $this->phoneCountry,
            'locale' => $this->locale,
        ];
    }
}
