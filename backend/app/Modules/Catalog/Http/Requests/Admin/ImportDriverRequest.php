<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\ImportDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Import endpoints scoped to one driver (preview, category mapping page). Defaults to
 * the first driver when omitted.
 */
class ImportDriverRequest extends FormRequest
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
            'driver' => ['sometimes', 'string', Rule::enum(ImportDriver::class)],
            'unmapped' => ['sometimes', 'boolean'],
        ];
    }

    public function driver(): ImportDriver
    {
        return ImportDriver::tryFrom((string) $this->validated('driver', '')) ?? ImportDriver::cases()[0];
    }
}
