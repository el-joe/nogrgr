<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\DTOs\ProductLabelData;
use App\Modules\Catalog\Enums\LabelPlaceholder;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\ProductLabelRequest;
use App\Modules\Catalog\Http\Requests\Admin\SortRequest;
use App\Modules\Catalog\Http\Requests\Admin\ToggleRequest;
use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Services\ProductLabelService;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Localization\Services\CountryService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Catalog › Labels (ADM-016 / 016a, Figma 65:27123 / 65:29682): sortable list with
 * active switch and shopper preview; create / edit in a modal with placeholder preview.
 */
class ProductLabelController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly ProductLabelService $labels,
        private readonly CatalogLocales $locales,
        private readonly CountryService $countries,
    ) {}

    public function index(): View
    {
        return view('catalog::admin.labels.index', [
            'labels' => $this->labels->ordered(),
            'countries' => $this->countries->options(),
            'renderer' => $this->labels,
        ]);
    }

    public function create(): View
    {
        return $this->form(new ProductLabel(['is_active' => true]));
    }

    public function store(ProductLabelRequest $request): JsonResponse
    {
        $label = $this->labels->create(ProductLabelData::fromArray($request->payload()));

        return JsonResponder::created(__('catalog::labels.created', ['name' => $label->text]), redirect: route('admin.catalog.labels.index'));
    }

    public function edit(ProductLabel $label): View
    {
        return $this->form($label);
    }

    public function update(ProductLabelRequest $request, ProductLabel $label): JsonResponse
    {
        $this->labels->update($label, ProductLabelData::fromArray($request->payload()));

        return JsonResponder::success(__('catalog::labels.updated', ['name' => $label->text]), redirect: route('admin.catalog.labels.index'));
    }

    public function toggle(ToggleRequest $request, ProductLabel $label): JsonResponse
    {
        $this->labels->setActive($label, $request->value());

        return JsonResponder::success(__($request->value() ? 'catalog::labels.activated' : 'catalog::labels.deactivated', ['name' => $label->text]));
    }

    public function destroy(ProductLabel $label): JsonResponse
    {
        $this->labels->delete($label);

        return JsonResponder::success(__('catalog::labels.deleted', ['name' => $label->text]), redirect: route('admin.catalog.labels.index'));
    }

    public function sort(SortRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->labels->saveOrder($request->ids()), __('catalog::labels.order_saved'));
    }

    private function form(ProductLabel $label): View
    {
        return view('catalog::admin.labels._form', [
            'label' => $label,
            'locales' => $this->locales->all(),
            'countries' => $this->countries->options(),
            'samples' => LabelPlaceholder::samples(),
        ]);
    }
}
