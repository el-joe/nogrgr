<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\DTOs\ProductLabelData;
use App\Modules\Catalog\Enums\LabelPlaceholder;
use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Repositories\Contracts\ProductLabelRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product labels (ADM-016 / 016a): icon + translated text with placeholders, country
 * targeting, active switch and drag-and-drop order.
 */
class ProductLabelService extends BaseService
{
    public function __construct(
        private readonly ProductLabelRepositoryContract $labels,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * @return Collection<int, ProductLabel>
     */
    public function ordered(): Collection
    {
        return $this->labels->ordered();
    }

    public function create(ProductLabelData $data): ProductLabel
    {
        return $this->labels->create([
            ...$this->columns($data),
            'sort_order' => $data->sortOrder ?? $this->labels->nextSortOrder(),
        ]);
    }

    public function update(ProductLabel $label, ProductLabelData $data): ProductLabel
    {
        return $this->labels->update($label, [
            ...$this->columns($data),
            'sort_order' => $data->sortOrder ?? $label->sort_order,
        ]);
    }

    public function setActive(ProductLabel $label, bool $active): ProductLabel
    {
        return $this->labels->update($label, ['is_active' => $active]);
    }

    public function delete(ProductLabel $label): void
    {
        $this->labels->delete($label);
    }

    /**
     * @param  list<int>  $ids  every label, in the new order
     */
    public function saveOrder(array $ids): void
    {
        $current = $this->labels->ids();
        $sorted = $ids;
        sort($current);
        sort($sorted);

        if ($current !== $sorted) {
            throw new DomainException(__('catalog::labels.order_stale'));
        }

        $this->transaction(fn () => $this->labels->saveOrder($ids), (new ProductLabel)->getConnectionName());
    }

    /**
     * Text with placeholders replaced: given values first, then the preview samples.
     *
     * @param  array<string, string>  $values  token => value, e.g. ['{stock}' => '3']
     */
    public function render(ProductLabel $label, ?string $locale = null, array $values = []): string
    {
        $text = (string) $label->getTranslation('text', $locale ?? app()->getLocale());

        return strtr($text, $values + LabelPlaceholder::samples());
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(ProductLabelData $data): array
    {
        $countries = $data->countryIds === null || $data->countryIds === []
            ? null
            : array_values(array_unique(array_map('intval', $data->countryIds)));

        return [
            'text' => $this->locales->forStorage($data->text),
            'icon' => $data->icon,
            'country_ids' => $countries,
            'is_active' => $data->isActive,
        ];
    }
}
