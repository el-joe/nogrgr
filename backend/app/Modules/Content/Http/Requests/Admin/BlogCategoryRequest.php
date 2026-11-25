<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\DTOs\BlogCategoryData;
use App\Modules\Content\Http\Requests\Admin\Concerns\TranslatedContent;
use App\Modules\Content\Models\BlogCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Blog category create / edit (ADM-080a).
 */
class BlogCategoryRequest extends FormRequest
{
    use TranslatedContent;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslated([], 'name');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            ...$this->translatedRules(['name' => [true, ['max:120']], 'description' => [false, ['max:500']]]),
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(BlogCategory::class, 'slug')->ignore($category instanceof BlogCategory ? $category->id : null)],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.en' => __('content::admin.categories.fields.name'),
            'slug' => __('content::admin.common.slug'),
        ];
    }

    public function toData(): BlogCategoryData
    {
        return new BlogCategoryData(
            slug: (string) $this->validated('slug'),
            name: $this->translations('name'),
            description: $this->translations('description'),
            isActive: $this->boolean('is_active'),
            sortOrder: (int) $this->validated('sort_order', 0),
        );
    }
}
