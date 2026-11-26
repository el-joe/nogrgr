<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Storefront\DTOs\Appearance\SocialLinkData;
use App\Modules\Storefront\Enums\SocialNetwork;
use App\Modules\Storefront\Models\StoreSocialLink;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Social link create / edit (VEN-034f): network (one link per network) and an https
 * profile URL on that network's own host.
 */
class SocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'url' => is_string($url) ? trim($url) : $url,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('link');

        return [
            'network' => ['required', Rule::enum(SocialNetwork::class), Rule::unique((new StoreSocialLink)->getTable(), 'network')->ignore(is_numeric($current) ? (int) $current : null)],
            'url' => ['required', 'string', 'max:500', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                $network = SocialNetwork::tryFrom((string) $this->input('network'));

                if ($network !== null && is_string($value) && ! $network->accepts($value)) {
                    $fail(__('content::store_appearance.validation.social_host', ['network' => $network->label()]));
                }
            }],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'network' => __('content::store_appearance.social.network'),
            'url' => __('content::store_appearance.social.url'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['network.unique' => __('content::store_appearance.validation.social_taken')];
    }

    public function toData(): SocialLinkData
    {
        return new SocialLinkData(
            network: SocialNetwork::from((string) $this->validated('network')),
            url: (string) $this->validated('url'),
            isActive: (bool) $this->validated('is_active'),
        );
    }
}
