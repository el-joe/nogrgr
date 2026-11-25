<?php

namespace App\Modules\AfterSales\Http\Controllers\Admin;

use App\Modules\AfterSales\Enums\ReturnShippingPayer;
use App\Modules\AfterSales\Http\Requests\ReturnPolicyRequest;
use App\Modules\AfterSales\Http\Requests\ReturnReasonRequest;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Commerce\Http\Requests\Admin\ToggleActiveRequest;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Storefront › Return policy (ADM-090, Figma 78:81991): platform default rules
 * (window, restocking fee, return shipping payer, non-returnable categories, video
 * evidence, exchanges) and the reasons list (add / edit modal, active switch, delete).
 */
class ReturnPolicyController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly ReturnPolicyService $policy,
        private readonly CategoryService $categories,
    ) {}

    public function edit(): View
    {
        return view('after-sales::admin.policy.edit', [
            'policy' => $this->policy->policy(),
            'reasons' => $this->policy->reasons(),
            'payers' => ReturnShippingPayer::options(),
            'categories' => $this->categories->parentOptions(),
        ]);
    }

    public function update(ReturnPolicyRequest $request): JsonResponse
    {
        $this->policy->update($request->toData());

        return JsonResponder::success(__('after-sales::policy.saved'));
    }

    public function createReason(Request $request): View
    {
        return $this->reasonForm($request, new ReturnReason(['is_active' => true]));
    }

    public function storeReason(ReturnReasonRequest $request): JsonResponse
    {
        $this->policy->saveReason($request->toData());

        return JsonResponder::created(__('after-sales::policy.reasons.saved'), redirect: route('admin.storefront.return-policy.edit'));
    }

    public function editReason(Request $request, int $reason): View
    {
        return $this->reasonForm($request, $this->policy->findReason($reason));
    }

    public function updateReason(ReturnReasonRequest $request, int $reason): JsonResponse
    {
        $this->policy->saveReason($request->toData(), $this->policy->findReason($reason));

        return JsonResponder::success(__('after-sales::policy.reasons.saved'), redirect: route('admin.storefront.return-policy.edit'));
    }

    public function toggleReason(ToggleActiveRequest $request, int $reason): JsonResponse
    {
        $this->policy->setReasonActive($this->policy->findReason($reason), $request->value());

        return JsonResponder::success(__('after-sales::policy.reasons.saved'));
    }

    public function destroyReason(int $reason): JsonResponse
    {
        $this->policy->deleteReason($this->policy->findReason($reason));

        return JsonResponder::success(__('after-sales::policy.reasons.deleted'), redirect: route('admin.storefront.return-policy.edit'));
    }

    private function reasonForm(Request $request, ReturnReason $reason): View
    {
        $title = $reason->exists ? __('after-sales::policy.reasons.edit') : __('after-sales::policy.reasons.add');

        return $this->remoteModal($request, 'after-sales::admin.policy._reason-form', [
            'reason' => $reason,
            'title' => $title,
            'action' => $reason->exists ? route('admin.storefront.return-policy.reasons.update', $reason->id) : route('admin.storefront.return-policy.reasons.store'),
        ], new RemoteModalPage('admin', $title, route('admin.storefront.return-policy.edit'), null, null, __('after-sales::common.save'), [
            __('navigation.admin.storefront') => null,
            __('navigation.admin.return_policy') => route('admin.storefront.return-policy.edit'),
            $title => null,
        ]));
    }
}
