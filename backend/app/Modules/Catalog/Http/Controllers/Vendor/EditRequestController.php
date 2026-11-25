<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\DataTables\Vendor\VendorEditRequestsDataTable;
use App\Modules\Catalog\DTOs\Requests\EditRequestSubmissionData;
use App\Modules\Catalog\Enums\EditableProductField;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Http\Requests\Vendor\EditRequestSubmitRequest;
use App\Modules\Catalog\Http\Requests\Vendor\ListFilterRequest;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Services\Requests\EditRequestService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Edit requests (VEN-013 history, VEN-013a submit modal): the vendor asks the platform to
 * change central content for its store; approval writes tenant overrides
 * (EditRequestService, decision #60c).
 */
class EditRequestController extends Controller
{
    public function __construct(
        private readonly EditRequestService $requests,
        private readonly StoreCatalogService $catalog,
    ) {}

    public function index(): View
    {
        return view('catalog::vendor.edit-requests.index');
    }

    public function data(ListFilterRequest $request, VendorEditRequestsDataTable $table): JsonResponse
    {
        $filters = $request->filters();
        $status = $request->input('request_status');

        if (is_string($status) && $status !== '') {
            $filters['status'] = $status;
        }

        return $table->toJson($filters);
    }

    /**
     * Modal body: product picker (or the given product) + field / value / reason / images,
     * with the current central values for the diff.
     */
    public function form(Request $request, StoreCatalogReader $reader): View
    {
        $productId = filter_var($request->query('product'), FILTER_VALIDATE_INT);
        $product = $productId !== false && $productId !== null ? $this->catalog->find((int) $productId) : null;
        $view = $product !== null ? $reader->view($product) : null;
        $central = $view?->central;
        $current = [];

        if ($central !== null) {
            foreach (EditableProductField::cases() as $field) {
                if ($field->isTranslatable()) {
                    foreach (array_keys(Locale::enabled()) as $locale) {
                        $override = $view?->override?->getTranslation($field->value, $locale, false);
                        $current[$field->value.':'.$locale] = (string) ($override !== null && $override !== '' ? $override : $central->getTranslation($field->value, $locale, false));
                    }
                } elseif ($field->isNumeric()) {
                    $current[$field->value] = (string) $view->weightGrams;
                }
            }
        }

        return view('catalog::vendor.edit-requests._form', [
            'product' => $product,
            'view' => $view,
            'current' => $current,
            'field' => is_string($request->query('field')) ? $request->query('field') : null,
        ]);
    }

    public function show(int $editRequest): View
    {
        /** @var EditRequest $found */
        $found = $this->requests->listing(['tenant_id' => StoreCatalogService::tenantId()])->with('product')->findOrFail($editRequest);

        return view('catalog::vendor.edit-requests._show', ['request' => $found]);
    }

    public function store(EditRequestSubmitRequest $request): JsonResponse
    {
        $product = $this->catalog->find((int) $request->validated('product_id'));
        $this->authorizeForUser($request->user('vendor'), 'update', $product);

        try {
            $editRequest = $this->requests->submit(new EditRequestSubmissionData(
                tenantId: StoreCatalogService::tenantId(),
                productId: (int) $product->central_product_id,
                variantId: null,
                values: $request->values(),
                images: $request->images(),
                vendorNote: $request->validated('note'),
                submittedBy: $request->user('vendor')?->getAttribute('name'),
            ));
        } catch (RequestRuleException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('catalog::vendor.edit_requests.submitted', ['number' => 'ER-'.$editRequest->id]), ['id' => $editRequest->id]);
    }
}
