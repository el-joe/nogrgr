<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Storefront\DTOs\Appearance\TrackingPixelsData;
use App\Modules\Storefront\Enums\TrackingPixel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Settings › Tracking pixels (VEN-083): per network an id in the network's format and
 * an on / off switch (on needs an id).
 */
class TrackingPixelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pixels = [];

        foreach (TrackingPixel::cases() as $pixel) {
            $row = (array) $this->input('pixels.'.$pixel->value, []);
            $id = $row['id'] ?? null;
            $pixels[$pixel->value] = [
                'id' => is_string($id) && trim($id) !== '' ? $pixel->normalize($id) : null,
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $this->merge(['pixels' => $pixels]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['pixels' => ['required', 'array']];

        foreach (TrackingPixel::cases() as $pixel) {
            $rules['pixels.'.$pixel->value.'.id'] = ['nullable', 'string', 'max:64', 'regex:'.$pixel->pattern(), 'required_if_accepted:pixels.'.$pixel->value.'.enabled'];
            $rules['pixels.'.$pixel->value.'.enabled'] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (TrackingPixel::cases() as $pixel) {
            $attributes['pixels.'.$pixel->value.'.id'] = __('content::store_tracking.id_of', ['pixel' => $pixel->label()]);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (TrackingPixel::cases() as $pixel) {
            $messages['pixels.'.$pixel->value.'.id.regex'] = __('content::store_tracking.invalid.'.$pixel->value);
            $messages['pixels.'.$pixel->value.'.id.required_if_accepted'] = __('content::store_tracking.required', ['pixel' => $pixel->label()]);
        }

        return $messages;
    }

    public function toData(): TrackingPixelsData
    {
        $pixels = [];

        foreach (TrackingPixel::cases() as $pixel) {
            $id = $this->validated('pixels.'.$pixel->value.'.id');
            $pixels[$pixel->value] = ['id' => is_string($id) ? $id : null, 'enabled' => (bool) $this->validated('pixels.'.$pixel->value.'.enabled')];
        }

        return new TrackingPixelsData($pixels);
    }
}
