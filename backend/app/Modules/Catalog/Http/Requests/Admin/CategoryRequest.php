<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Category create / edit (ADM-014a). Name required in ar + en (other active languages
 * optional); thumbnail through x-ui::image-upload (thumbnail[] new file,
 * thumbnail_keep[] = "current"). Re-parenting cycles are rejected by CategoryService.
 */
class CategoryRequest extends FormRequest
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
        $locales = app(CatalogLocales::class);
        $category = $this->route('category');

        return [
            ...$locales->rules('name', ['string', 'max:120']),
            ...$locales->rules('description', ['string', 'max:2000'], false),
            ...$locales->rules('meta_title', ['string', 'max:160'], false),
            ...$locales->rules('meta_description', ['string', 'max:320'], false),
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists(Category::class, 'id')->whereNull('deleted_at'),
                $category instanceof Category ? Rule::notIn([$category->id]) : 'integer',
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
            'is_featured' => ['required', 'boolean'],
            'show_in_menu' => ['required', 'boolean'],
            'thumbnail' => ['nullable', 'array', 'max:1'],
            'thumbnail.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=400,min_height=400'],
            'thumbnail_keep' => ['nullable', 'array', 'max:1'],
            'thumbnail_keep.*' => ['string', 'in:current'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('catalog::categories.fields.name'),
            'description' => __('catalog::categories.fields.description'),
            'meta_title' => __('catalog::categories.fields.meta_title'),
            'meta_description' => __('catalog::categories.fields.meta_description'),
            'slug' => __('catalog::categories.fields.slug'),
            'parent_id' => __('catalog::categories.fields.parent'),
            'sort_order' => __('catalog::categories.fields.sort_order'),
            'status' => __('catalog::categories.fields.status'),
            'thumbnail' => __('catalog::categories.fields.thumbnail'),
            'thumbnail.*' => __('catalog::categories.fields.thumbnail'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['parent_id.not_in' => __('catalog::categories.parent_cycle')];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $locales = app(CatalogLocales::class);
        $file = $this->file('thumbnail.0');

        return [
            'name' => $locales->clean($this->validated('name')),
            'description' => $locales->clean($this->validated('description')),
            'meta_title' => $locales->clean($this->validated('meta_title')),
            'meta_description' => $locales->clean($this->validated('meta_description')),
            'slug' => $this->filled('slug') ? (string) $this->validated('slug') : null,
            'parent_id' => $this->filled('parent_id') ? (int) $this->validated('parent_id') : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->validated('sort_order') : null,
            'status' => (string) $this->validated('status'),
            'is_featured' => $this->boolean('is_featured'),
            'show_in_menu' => $this->boolean('show_in_menu'),
            'thumbnail' => $file instanceof UploadedFile ? $file : null,
            'keep_thumbnail' => in_array('current', (array) $this->input('thumbnail_keep', []), true),
        ];
    }
}
