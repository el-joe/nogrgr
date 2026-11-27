<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Support\Str;

/**
 * Validate-on-blur of the product form: runs only the ProductRequest rules of `field`
 * (e.g. "sku", "name.en", "variants.3.price") against the posted form.
 */
class ProductFieldRequest extends ProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $field = $this->field();
        $rules = ['field' => ['required', 'string', 'max:120']];

        foreach (parent::rules() as $key => $rule) {
            $pattern = '/^'.str_replace('\*', '[^.]+', preg_quote($key, '/')).'$/';

            if ($key === $field || preg_match($pattern, $field) === 1 || Str::startsWith($key, $field.'.')) {
                $rules[$key] = $rule;
            }
        }

        return $rules;
    }

    /**
     * The category leaf check runs only when the categories field itself is validated.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return str_starts_with($this->field(), 'category_ids') ? parent::after() : [];
    }

    public function field(): string
    {
        $field = $this->input('field');

        return is_string($field) ? str_replace(['[', ']'], ['.', ''], $field) : '';
    }
}
