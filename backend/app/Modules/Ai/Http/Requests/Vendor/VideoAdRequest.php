<?php

namespace App\Modules\Ai\Http\Requests\Vendor;

use App\Modules\Ai\DTOs\VideoAdRequestData;
use App\Modules\Ai\Enums\VideoAdAspect;
use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Enums\VideoAdPresenter;
use App\Modules\Ai\Enums\VideoAdTone;
use App\Modules\Ai\Enums\VideoAdType;
use App\Modules\Ai\Enums\VideoAdVoice;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\VideoAds\VideoAdStudioService;
use App\Modules\Localization\Rules\StoreCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * AI Advertising Studio "Generate" (VEN-010c): the four wizard steps — product, video
 * type, people and voice (presenter, voice, tone, one of the store's enabled languages),
 * platform and size (size must suit the platform, 15 / 30 s, optional selling country) —
 * plus optional notes for the script writer.
 */
class VideoAdRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::enum(VideoAdType::class)],
            'presenter' => ['required', Rule::enum(VideoAdPresenter::class)],
            'voice' => ['required', Rule::enum(VideoAdVoice::class)],
            'tone' => ['required', Rule::enum(VideoAdTone::class)],
            'locale' => ['required', 'string', Rule::in(array_keys(app(ProductAiTranslationService::class)->storeLanguages()))],
            'platform' => ['required', Rule::enum(VideoAdPlatform::class)],
            'aspect' => ['required', Rule::enum(VideoAdAspect::class)],
            'duration' => ['required', 'integer', Rule::in(VideoAdStudioService::DURATIONS)],
            'country_id' => ['nullable', 'integer', new StoreCountry],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $platform = VideoAdPlatform::tryFrom((string) $this->input('platform'));
                $aspect = VideoAdAspect::tryFrom((string) $this->input('aspect'));

                if ($platform !== null && $aspect !== null && ! $platform->supports($aspect)) {
                    $validator->errors()->add('aspect', (string) __('ai::ad_studio.errors.aspect', ['platform' => $platform->label()]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => (string) __('ai::ad_studio.fields.product'),
            'type' => (string) __('ai::ad_studio.steps.type'),
            'presenter' => (string) __('ai::ad_studio.fields.presenter'),
            'voice' => (string) __('ai::ad_studio.fields.voice'),
            'tone' => (string) __('ai::ad_studio.fields.tone'),
            'locale' => (string) __('ai::ad_studio.fields.language'),
            'platform' => (string) __('ai::ad_studio.fields.platform'),
            'aspect' => (string) __('ai::ad_studio.fields.size'),
            'duration' => (string) __('ai::ad_studio.fields.duration'),
            'country_id' => (string) __('ai::ad_studio.fields.market'),
            'notes' => (string) __('ai::ad_studio.fields.notes'),
        ];
    }

    public function toData(): VideoAdRequestData
    {
        $country = $this->validated('country_id');
        $notes = trim((string) $this->validated('notes'));

        return new VideoAdRequestData(
            productId: (int) $this->validated('product_id'),
            type: VideoAdType::from((string) $this->validated('type')),
            presenter: VideoAdPresenter::from((string) $this->validated('presenter')),
            voice: VideoAdVoice::from((string) $this->validated('voice')),
            tone: VideoAdTone::from((string) $this->validated('tone')),
            locale: (string) $this->validated('locale'),
            platform: VideoAdPlatform::from((string) $this->validated('platform')),
            aspect: VideoAdAspect::from((string) $this->validated('aspect')),
            duration: (int) $this->validated('duration'),
            countryId: $country !== null ? (int) $country : null,
            notes: $notes !== '' ? $notes : null,
        );
    }
}
