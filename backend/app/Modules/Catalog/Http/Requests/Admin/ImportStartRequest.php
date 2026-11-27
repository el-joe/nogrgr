<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\Imports\StartImportData;
use App\Modules\Catalog\Enums\ImportDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Run import" (ADM-013): driver, refresh already imported products, optional page limit
 * and whether unmapped source categories are created in the central catalog (default).
 */
class ImportStartRequest extends FormRequest
{
    public const MAX_PAGES = 10000;

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
            'driver' => ['required', 'string', Rule::enum(ImportDriver::class)],
            'update_existing' => ['sometimes', 'boolean'],
            'auto_create' => ['sometimes', 'boolean'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGES],
        ];
    }

    public function toData(): StartImportData
    {
        return new StartImportData(
            ImportDriver::from((string) $this->validated('driver')),
            $this->boolean('update_existing', true),
            $this->filled('max_pages') ? (int) $this->validated('max_pages') : null,
            autoCreate: $this->boolean('auto_create', true),
        );
    }
}
