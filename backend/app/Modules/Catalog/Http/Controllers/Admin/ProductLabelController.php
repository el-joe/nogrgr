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
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Catalog › Labels (ADM-016 / 016a, Figma 65:27123 / 65:29682): sortable list with
 * active switch and shopper preview; create / edit in a modal with placeholder preview.
 */
class ProductLabelController extends Controller
{
    use AnswersDomainErrors;
    use RendersRemoteModals;

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

    public function create(Request $request): View
    {
        return $this->form($request, new ProductLabel(['is_active' => true]));
    }

    public function store(ProductLabelRequest $request): JsonResponse
    {
        $label = $this->labels->create(ProductLabelData::fromArray($request->payload()));

        return JsonResponder::created(__('catalog::labels.created', ['name' => $label->text]), redirect: route('admin.catalog.labels.index'));
    }

    public function edit(Request $request, ProductLabel $label): View
    {
        return $this->form($request, $label);
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

    private function form(Request $request, ProductLabel $label): View
    {
        $title = __($label->exists ? 'catalog::labels.edit_title' : 'catalog::labels.create_title');
        $index = route('admin.catalog.labels.index');

        return $this->remoteModal($request, 'catalog::admin.labels._form', [
            'label' => $label,
            'locales' => $this->locales->all(),
            'countries' => $this->countries->options(),
            'samples' => LabelPlaceholder::samples(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            pageModule: 'admin-catalog',
            formId: 'label-form',
            submitLabel: __('catalog::common.save'),
            breadcrumbs: [__('navigation.sections.catalog') => null, __('catalog::labels.title') => $index, $title => null],
        ));
    }
}
