<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\Enums\EditableProductField;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Submit edit request modal (VEN-013a): one central field (+ locale for text fields), the
 * new value (text / weight in grams) or new images, and the reason.
 */
class EditRequestSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The modal sends one select value "field[:locale]" (e.g. "name:ar").
     */
    protected function prepareForValidation(): void
    {
        $key = $this->input('field_key');

        if (is_string($key) && $key !== '' && ! $this->filled('field')) {
            [$field, $locale] = array_pad(explode(':', $key, 2), 2, null);
            $this->merge(['field' => $field, 'locale' => $locale]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $field = EditableProductField::tryFrom((string) $this->input('field'));
        $images = $field === EditableProductField::Images;
        $numeric = $field?->isNumeric() ?? false;

        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'field' => ['required', Rule::enum(EditableProductField::class)],
            'locale' => [$images || $numeric ? 'nullable' : 'required', Rule::in(array_keys(Locale::enabled()))],
            'value' => $numeric ? ['required', 'integer', 'min:1', 'max:1000000'] : [$images ? 'nullable' : 'required', 'string', 'max:10000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'images' => [$images ? 'required' : 'nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function field(): EditableProductField
    {
        return EditableProductField::from((string) $this->validated('field'));
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function values(): array
    {
        $field = $this->field();

        return match (true) {
            $field->isTranslatable() => [$field->value => [(string) $this->validated('locale') => (string) $this->validated('value')]],
            $field->isNumeric() => [$field->value => ['' => (string) (int) $this->validated('value')]],
            default => [],
        };
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        return $this->field() === EditableProductField::Images ? array_values(array_filter((array) $this->file('images', []), static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
    }
}
