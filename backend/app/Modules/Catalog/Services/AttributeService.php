<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\DTOs\AttributeData;
use App\Modules\Catalog\DTOs\AttributeOptionData;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Services\BaseService;
use DomainException;

/**
 * Variant attributes (ADM-015 / 015a): name, display type and the ordered, translated
 * options repeater. Options used by a variant cannot be removed (deactivate them), and
 * an attribute used by products cannot be deleted.
 */
class AttributeService extends BaseService
{
    public function __construct(
        private readonly AttributeRepositoryContract $attributes,
        private readonly CatalogLocales $locales,
    ) {}

    public function count(): int
    {
        return $this->attributes->query()->count();
    }

    /**
     * @return array{products: int, variants: int}
     */
    public function usage(Attribute $attribute): array
    {
        return $this->attributes->usage($attribute);
    }

    /**
     * Option ids of the attribute that variants use (locked rows of the repeater).
     *
     * @return list<int>
     */
    public function lockedOptionIds(Attribute $attribute): array
    {
        return $this->attributes->optionsInUse(array_map('intval', $attribute->options->pluck('id')->all()));
    }

    public function create(AttributeData $data): Attribute
    {
        return $this->transaction(function () use ($data): Attribute {
            $attribute = $this->attributes->create([
                ...$this->columns($data),
                'sort_order' => $this->attributes->nextSortOrder(),
            ]);
            $this->syncOptions($attribute, $data);

            return $attribute->load('options');
        }, $this->connection());
    }

    public function update(Attribute $attribute, AttributeData $data): Attribute
    {
        return $this->transaction(function () use ($attribute, $data): Attribute {
            $this->attributes->update($attribute, $this->columns($data));
            $this->syncOptions($attribute, $data);

            return $attribute->load('options');
        }, $this->connection());
    }

    public function delete(Attribute $attribute): void
    {
        if ($this->attributes->usage($attribute)['products'] > 0) {
            throw new DomainException(__('catalog::attributes.delete_in_use', ['name' => $attribute->name]));
        }

        $this->transaction(function () use ($attribute): void {
            $this->attributes->deleteOptions($attribute, array_map('intval', $attribute->options()->pluck('id')->all()));
            $this->attributes->delete($attribute);
        }, $this->connection());
    }

    private function syncOptions(Attribute $attribute, AttributeData $data): void
    {
        $existing = $attribute->options()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($data->options) as $position => $option) {
            /** @var AttributeOptionData $option */
            $values = [
                'name' => $this->locales->forStorage($option->name),
                'swatch' => $data->displayType->usesSwatch() ? $option->swatch : null,
                'is_active' => $option->isActive,
                'sort_order' => $position,
            ];

            if ($option->id !== null) {
                $current = $existing->get($option->id);

                if ($current === null) {
                    throw new DomainException(__('catalog::attributes.option_unknown'));
                }

                $this->attributes->updateOption($current, $values);
                $kept[] = $option->id;

                continue;
            }

            $this->attributes->createOption($attribute, $values);
        }

        $removed = array_values(array_diff(array_map('intval', $existing->keys()->all()), $kept));
        $locked = $this->attributes->optionsInUse($removed);

        if ($locked !== []) {
            throw new DomainException(__('catalog::attributes.option_in_use', [
                'name' => (string) $existing->get($locked[0])?->name,
            ]));
        }

        $this->attributes->deleteOptions($attribute, $removed);
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(AttributeData $data): array
    {
        return [
            'name' => $this->locales->forStorage($data->name),
            'display_type' => $data->displayType,
            'status' => $data->status,
        ];
    }

    private function connection(): ?string
    {
        return (new Attribute)->getConnectionName();
    }
}
