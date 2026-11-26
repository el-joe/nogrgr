<?php

namespace App\Modules\Affiliates\Http\Controllers\Admin;

use App\Modules\Affiliates\DataTables\PromoCodesDataTable;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Http\Requests\AffiliateSearchRequest;
use App\Modules\Affiliates\Http\Requests\PromoCodeRequest;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Modules\Affiliates\Services\PromoCodeService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Affiliates › Promo codes (ADM-063, Figma 74:67899 · 74:68877): list with
 * search + state filter, create / edit modal (affiliate, code, discount, valid until,
 * commission, minimum spend, usage limit, active), delete.
 */
class PromoCodeController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly PromoCodeRepositoryContract $codes,
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly PromoCodeService $service,
    ) {}

    public function index(): View
    {
        return view('affiliates::admin.promo-codes.index');
    }

    public function data(Request $request, PromoCodesDataTable $table): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $table->abilities($admin->can('affiliates.promo-codes.manage'))->toJsonFromRequest($request, PromoCodesDataTable::FILTERS);
    }

    public function affiliates(AffiliateSearchRequest $request): JsonResponse
    {
        return response()->json($this->affiliates->search($request->term(), $request->page()));
    }

    public function create(Request $request): View
    {
        return $this->remoteModal($request, 'affiliates::admin.promo-codes._form', [
            'code' => null,
            'types' => RateType::options(),
            'formAction' => route('admin.affiliates.promo-codes.store'),
        ], $this->page((string) __('affiliates::admin.promo.create')));
    }

    public function store(PromoCodeRequest $request): JsonResponse
    {
        $code = $this->service->create($request->toData());

        return JsonResponder::created((string) __('affiliates::admin.promo.created', ['code' => $code->code]));
    }

    public function edit(Request $request, int $promoCode): View
    {
        $code = $this->find($promoCode);
        $code->loadMissing('affiliate');

        return $this->remoteModal($request, 'affiliates::admin.promo-codes._form', [
            'code' => $code,
            'types' => RateType::options(),
            'formAction' => route('admin.affiliates.promo-codes.update', $code->id),
        ], $this->page($code->code));
    }

    public function update(PromoCodeRequest $request, int $promoCode): JsonResponse
    {
        $code = $this->service->update($this->find($promoCode), $request->toData());

        return JsonResponder::success((string) __('affiliates::admin.promo.updated', ['code' => $code->code]));
    }

    public function destroy(int $promoCode): JsonResponse
    {
        $code = $this->find($promoCode);
        $this->service->delete($code);

        return JsonResponder::success((string) __('affiliates::admin.promo.deleted', ['code' => $code->code]));
    }

    private function find(int $id): AffiliatePromoCode
    {
        /** @var AffiliatePromoCode|null $code */
        $code = $this->codes->find($id);

        return $code ?? abort(404);
    }

    private function page(string $title): RemoteModalPage
    {
        return new RemoteModalPage('admin', $title, route('admin.affiliates.promo-codes.index'), 'admin-affiliates', null, null, [
            __('navigation.admin.affiliates') => null,
            __('navigation.admin.promo_codes') => route('admin.affiliates.promo-codes.index'),
            $title => null,
        ]);
    }
}
