<?php

namespace App\Modules\Storefront\Http\Requests\StoreApi;

use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\SalesPeriod;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store API product list / search: entry point (`kind` or `category` slug), sort, page,
 * filters — the same filters as the theme listing pages. Prices are USD minor units.
 */
class ProductIndexRequest extends FormRequest
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
            'kind' => ['nullable', Rule::in([ListingKind::NewIn->value, ListingKind::BestSelling->value, ListingKind::TopRated->value, ListingKind::Offers->value])],
            'category' => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::enum(ListingSort::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'period' => ['nullable', Rule::enum(SalesPeriod::class)],
            'price_min' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'price_max' => ['nullable', 'integer', 'min:0', 'max:999999999', function (string $attribute, mixed $value, Closure $fail): void {
                $min = $this->input('price_min');

                if (is_numeric($min) && is_numeric($value) && (int) $value < (int) $min) {
                    $fail(__('validation.gte.numeric', ['attribute' => $attribute, 'value' => $min]));
                }
            }],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'in_stock' => ['nullable', 'boolean'],
            'flash' => ['nullable', 'boolean'],
            'added' => ['nullable', 'integer', Rule::in([7, 30])],
            'discount' => ['nullable', 'integer', Rule::in([10, 20, 30, 50])],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['integer', 'min:1'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*' => ['array', 'max:20'],
            'options.*.*' => ['string', 'max:120'],
        ];
    }

    public function kind(): ListingKind
    {
        $kind = $this->validated('kind');

        return is_string($kind) ? ListingKind::from($kind) : ListingKind::NewIn;
    }

    public function toQuery(ListingKind $kind): ListingQuery
    {
        $sort = ListingSort::tryFrom((string) $this->validated('sort'));
        $options = [];

        foreach ((array) $this->validated('options', []) as $attribute => $values) {
            if (is_numeric($attribute) && $values !== []) {
                $options[(int) $attribute] = array_values(array_map('strval', (array) $values));
            }
        }

        return new ListingQuery(
            kind: $kind,
            sort: $sort !== null && in_array($sort, $kind->sorts(), true) ? $sort : $kind->defaultSort(),
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? ListingQuery::PER_PAGE),
            term: ListingQuery::term($this->validated('q')),
            period: SalesPeriod::tryFrom((int) ($this->validated('period') ?? SalesPeriod::Days30->value)) ?? SalesPeriod::Days30,
            categoryIds: array_values(array_map('intval', (array) $this->validated('categories', []))),
            priceMin: $this->validated('price_min') !== null ? (int) $this->validated('price_min') : null,
            priceMax: $this->validated('price_max') !== null ? (int) $this->validated('price_max') : null,
            minRating: $this->validated('rating') !== null ? (int) $this->validated('rating') : null,
            inStockOnly: $this->boolean('in_stock'),
            options: $options,
            addedWithinDays: $this->validated('added') !== null ? (int) $this->validated('added') : null,
            flashOnly: $this->boolean('flash'),
            minDiscount: $this->validated('discount') !== null ? (int) $this->validated('discount') : null,
        );
    }
}
