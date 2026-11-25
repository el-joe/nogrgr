<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Archive / restore a product request: {archived: 0|1}.
 */
class RequestArchiveRequest extends FormRequest
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
        return ['archived' => ['required', 'boolean']];
    }

    public function archived(): bool
    {
        return $this->boolean('archived');
    }
}
