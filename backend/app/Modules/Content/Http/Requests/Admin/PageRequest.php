<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\DTOs\PageData;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Http\Requests\Admin\Concerns\TranslatedContent;
use App\Modules\Content\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Static page create / edit (ADM-082a): translations + SEO, slug, target
 * (central / storefront / both), compliance flag, status.
 */
class PageRequest extends FormRequest
{
    use TranslatedContent;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslated(['body']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        return [
            ...$this->translatedRules([
                'title' => [true, ['max:200']],
                'body' => [true, ['max:200000']],
                'meta_title' => [false, ['max:70']],
                'meta_description' => [false, ['max:170']],
            ]),
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Page::class, 'slug')->ignore($page instanceof Page ? $page->id : null)],
            'target' => ['required', Rule::enum(PageTarget::class)],
            'is_compliance' => ['nullable', 'boolean'],
            'status' => ['required', Rule::enum(PageStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.en' => __('content::admin.pages.fields.title'),
            'body.en' => __('content::admin.pages.fields.body'),
            'slug' => __('content::admin.common.slug'),
            'target' => __('content::admin.pages.fields.target'),
            'status' => __('content::admin.pages.fields.status'),
        ];
    }

    public function toData(): PageData
    {
        return new PageData(
            slug: (string) $this->validated('slug'),
            title: $this->translations('title'),
            body: $this->translations('body'),
            metaTitle: $this->translations('meta_title'),
            metaDescription: $this->translations('meta_description'),
            target: PageTarget::from((string) $this->validated('target')),
            isCompliance: $this->boolean('is_compliance'),
            status: PageStatus::from((string) $this->validated('status')),
        );
    }
}
