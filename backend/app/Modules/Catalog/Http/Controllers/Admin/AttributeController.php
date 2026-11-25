<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\DataTables\AttributesDataTable;
use App\Modules\Catalog\DTOs\AttributeData;
use App\Modules\Catalog\DTOs\AttributeOptionData;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\AttributeRequest;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Services\AttributeService;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Catalog › Attributes (ADM-015 / 015a, Figma 65:25447 / 65:26338): server-side
 * list, create / edit with the translated options repeater, delete.
 */
class AttributeController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly AttributeService $attributes,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(): View
    {
        return view('catalog::admin.attributes.index', ['total' => $this->attributes->count()]);
    }

    public function data(Request $request, AttributesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, AttributesDataTable::FILTERS);
    }

    public function create(): View
    {
        return view('catalog::admin.attributes.form', [
            'attribute' => new Attribute,
            'usage' => null,
            'locked' => [],
            'locales' => $this->locales->all(),
        ]);
    }

    public function store(AttributeRequest $request): JsonResponse
    {
        try {
            $attribute = $this->attributes->create($this->attributeData($request));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('catalog::attributes.created', ['name' => $attribute->name]), redirect: route('admin.catalog.attributes.index'));
    }

    public function edit(Attribute $attribute): View
    {
        $attribute->load('options');

        return view('catalog::admin.attributes.form', [
            'attribute' => $attribute,
            'usage' => $this->attributes->usage($attribute),
            'locked' => $this->attributes->lockedOptionIds($attribute),
            'locales' => $this->locales->all(),
        ]);
    }

    public function update(AttributeRequest $request, Attribute $attribute): JsonResponse
    {
        return $this->attempt(
            fn () => $this->attributes->update($attribute, $this->attributeData($request)),
            __('catalog::attributes.updated', ['name' => $attribute->name]),
            route('admin.catalog.attributes.index'),
        );
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        return $this->attempt(
            fn () => $this->attributes->delete($attribute),
            __('catalog::attributes.deleted', ['name' => $attribute->name]),
        );
    }

    private function attributeData(AttributeRequest $request): AttributeData
    {
        $payload = $request->payload();
        $payload['options'] = array_map(static fn (array $option): AttributeOptionData => AttributeOptionData::fromArray($option), $payload['options']);

        return AttributeData::fromArray($payload);
    }
}
