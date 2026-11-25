<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\DTOs\BadgeAssignmentData;
use App\Modules\Catalog\DTOs\BadgeSettingsData;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\BadgeAssignRequest;
use App\Modules\Catalog\Http\Requests\Admin\BadgeSettingsRequest;
use App\Modules\Catalog\Http\Requests\Admin\ProductSearchRequest;
use App\Modules\Catalog\Http\Requests\Admin\SortRequest;
use App\Modules\Catalog\Http\Resources\BadgeProductSearchResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\BadgeService;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Catalog › Badges hub (ADM-017…017g, Figma 65:27887 / 65:29682): one tab per
 * badge, sortable product list, remove, assign modal (search or whole category) and
 * the badge settings card.
 */
class BadgeController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly BadgeService $badges,
        private readonly CategoryService $categories,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(): View
    {
        return $this->page(BadgeType::cases()[0]);
    }

    public function show(BadgeType $type): View
    {
        return $this->page($type);
    }

    public function update(BadgeSettingsRequest $request, BadgeType $type): JsonResponse
    {
        $badge = $this->badges->updateSettings($this->badges->badge($type), BadgeSettingsData::fromArray($request->payload()));

        return JsonResponder::success(__('catalog::badges.settings_saved', ['name' => $badge->name]));
    }

    public function search(ProductSearchRequest $request, BadgeType $type): JsonResponse
    {
        $results = $this->badges->searchProducts($this->badges->badge($type), $request->term(), $request->page());

        return response()->json([
            'data' => BadgeProductSearchResource::collection($results->getCollection())->resolve($request),
            'meta' => ['page' => $results->currentPage(), 'more' => $results->hasMorePages(), 'total' => $results->total()],
        ]);
    }

    public function assign(BadgeAssignRequest $request, BadgeType $type): JsonResponse
    {
        try {
            $added = $this->badges->assign($this->badges->badge($type), BadgeAssignmentData::fromArray($request->payload()));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(
            trans_choice('catalog::badges.assigned', $added, ['count' => $added]),
            ['added' => $added],
            route('admin.catalog.badges.show', $type),
        );
    }

    public function remove(BadgeType $type, Product $product): JsonResponse
    {
        return $this->attempt(
            fn () => $this->badges->remove($this->badges->badge($type), $product->id),
            __('catalog::badges.removed', ['name' => $product->name]),
            route('admin.catalog.badges.show', $type),
        );
    }

    public function sort(SortRequest $request, BadgeType $type): JsonResponse
    {
        return $this->attempt(
            fn () => $this->badges->saveOrder($this->badges->badge($type), $request->ids()),
            __('catalog::badges.order_saved'),
        );
    }

    private function page(BadgeType $type): View
    {
        $tabs = $this->badges->tabs();
        $badge = $tabs[$type->value]['badge'];

        return view('catalog::admin.badges.index', [
            'type' => $type,
            'badge' => $badge,
            'tabs' => $tabs,
            'assignments' => $this->badges->assignments($badge),
            'categories' => $this->categories->optionsWithCounts(),
            'locales' => $this->locales->all(),
        ]);
    }
}
