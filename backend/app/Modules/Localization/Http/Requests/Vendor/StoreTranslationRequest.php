<?php

namespace App\Modules\Localization\Http\Requests\Vendor;

use App\Modules\Localization\Enums\StoreContentType;
use App\Modules\Localization\Services\StoreTranslationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vendor › Settings › Store translations (VEN-075) inputs. `scope` picks the rule set:
 * static (key + locale), static_value (+ value), missing (locale only), content
 * (type + id + field + locale), content_value (+ value). The locale must be one of the
 * store's languages.
 */
class StoreTranslationRequest extends FormRequest
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
        $route = (string) $this->route()?->getName();
        $locale = ['required', 'string', 'max:12', static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! app(StoreTranslationService::class)->isStoreLocale($value)) {
                $fail(__('localization::vendor_translations.errors.unknown_locale'));
            }
        }];
        $static = ['key' => ['required', 'string', 'max:191'], 'locale' => $locale];
        $content = [
            'type' => ['required', 'string', Rule::enum(StoreContentType::class)],
            'id' => ['required', 'integer', 'min:1'],
            'field' => ['required', 'string', 'max:64'],
            'locale' => $locale,
        ];

        return match (true) {
            str_ends_with($route, 'static.update') => $static + ['value' => ['required', 'string', 'max:5000']],
            str_ends_with($route, 'translate-missing') => ['locale' => $locale],
            str_ends_with($route, 'content.update') => $content + ['value' => ['nullable', 'string', 'max:20000']],
            str_contains($route, '.content.') => $content,
            default => $static,
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => (string) __('localization::vendor_translations.columns.key'),
            'locale' => (string) __('localization::vendor_translations.fields.language'),
            'value' => (string) __('localization::vendor_translations.fields.value'),
            'type' => (string) __('localization::vendor_translations.fields.type'),
            'field' => (string) __('localization::vendor_translations.fields.field'),
        ];
    }

    public function key(): string
    {
        return (string) $this->validated('key');
    }

    public function locale(): string
    {
        return (string) $this->validated('locale');
    }

    public function value(): string
    {
        return (string) ($this->validated('value') ?? '');
    }

    public function type(): StoreContentType
    {
        return StoreContentType::from((string) $this->validated('type'));
    }

    public function recordId(): int
    {
        return (int) $this->validated('id');
    }

    public function field(): string
    {
        return (string) $this->validated('field');
    }
}
