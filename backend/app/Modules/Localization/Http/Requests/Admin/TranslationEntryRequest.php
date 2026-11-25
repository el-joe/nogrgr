<?php

namespace App\Modules\Localization\Http\Requests\Admin;

/**
 * One translation key of a file (view / edit / revert / lock).
 */
class TranslationEntryRequest extends TranslationQueryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'file' => ['required', 'string', 'max:96'],
            'locked' => ['sometimes', 'boolean'],
        ]);
    }

    protected function keyRequired(): bool
    {
        return true;
    }

    public function locked(): bool
    {
        return $this->boolean('locked');
    }
}
