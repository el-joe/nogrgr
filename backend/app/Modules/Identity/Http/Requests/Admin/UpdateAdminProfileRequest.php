<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Models\Admin;
use App\Shared\Support\Locale;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * My profile (ADM-005): name, email (unique), language, timezone, avatar
 * (x-ui::image-upload: avatar[] new file, avatar_keep[] kept current image).
 */
class UpdateAdminProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Admin $admin */
        $admin = $this->user('admin');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique(Admin::class, 'email')->ignore($admin->getKey())],
            'locale' => ['nullable', 'string', Rule::in(array_keys(Locale::enabled()))],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'avatar' => ['nullable', 'array', 'max:1'],
            'avatar.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64'],
            'avatar_keep' => ['nullable', 'array', 'max:1'],
            'avatar_keep.*' => ['string', 'in:current'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('identity::profile.fields.name'),
            'email' => __('identity::profile.fields.email'),
            'locale' => __('identity::profile.fields.language'),
            'timezone' => __('identity::profile.fields.timezone'),
            'avatar' => __('identity::profile.fields.avatar'),
            'avatar.*' => __('identity::profile.fields.avatar'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $file = $this->file('avatar.0');

        return [
            'name' => trim((string) $this->validated('name')),
            'email' => (string) $this->validated('email'),
            'locale' => $this->filled('locale') ? (string) $this->validated('locale') : null,
            'timezone' => $this->filled('timezone') ? (string) $this->validated('timezone') : null,
            'avatar' => $file instanceof UploadedFile ? $file : null,
            'keep_avatar' => in_array('current', (array) $this->input('avatar_keep', []), true),
        ];
    }
}
