<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Vendor\SortRequest;
use App\Modules\Catalog\Http\Requests\Vendor\TaxonomyProductsRequest;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyPageService;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyReader;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Products › Badges (VEN-018 / 018a / 018b, Vendor Panel (new screens) 83:103600,
 * decision #74): one tab per active central badge; the platform's products on it are
 * read-only, the store adds products (or a whole category), removes and orders its own.
 */
class StoreBadgeController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly StoreTaxonomyService $taxonomy,
        private readonly StoreTaxonomyPageService $pages,
    ) {}

    public function index(StoreTaxonomyReader $reader): View
    {
        $badges = $this->taxonomy->badges();

        return $this->page($badges[0] ?? null, $reader);
    }

    public function show(int $badge, StoreTaxonomyReader $reader): View
    {
        return $this->page($this->badge($badge), $reader);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('q');

        return response()->json($this->pages->searchProducts(is_string($term) ? mb_substr($term, 0, 100) : null, (int) $request->query('page', '1')));
    }

    public function add(TaxonomyProductsRequest $request, int $badge): JsonResponse
    {
        $current = $this->badge($badge);

        try {
            $added = $request->routeIs('*.add-category')
                ? $this->taxonomy->addCategoryToBadge($current, $request->categoryRef())
                : $this->taxonomy->addToBadge($current, $request->productIds());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(trans_choice('catalog::taxonomy.badges.added', $added, ['count' => $added, 'badge' => $current->name]), redirect: vendor_route('catalog.badges.show', ['badge' => $current->id]));
    }

    public function remove(int $badge, int $product): JsonResponse
    {
        $current = $this->badge($badge);

        return $this->attempt(fn () => $this->taxonomy->removeFromBadge($current, $product), (string) __('catalog::taxonomy.badges.removed', ['badge' => $current->name]));
    }

    public function sort(SortRequest $request, int $badge): JsonResponse
    {
        $current = $this->badge($badge);

        return $this->attempt(fn () => $this->taxonomy->sortBadge($current, $request->ids()), (string) __('catalog::taxonomy.sort.saved'));
    }

    private function badge(int $id): ProductBadge
    {
        foreach ($this->taxonomy->badges() as $badge) {
            if ($badge->id === $id) {
                return $badge;
            }
        }

        abort(404);
    }

    private function page(?ProductBadge $badge, StoreTaxonomyReader $reader): View
    {
        $members = $badge !== null ? $reader->badgeMembers($badge) : ['admin' => [], 'store' => []];

        return view('catalog::vendor.badges.index', [
            'badge' => $badge,
            'tabs' => $this->pages->badgeTabs(),
            'adminRows' => $this->pages->sortRows($members['admin']),
            'storeRows' => $this->pages->sortRows($members['store']),
            'categoryOptions' => $badge !== null ? $this->pages->categoryOptions() : [],
        ]);
    }
}
