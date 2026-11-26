<?php

namespace App\Modules\AfterSales\Http\Controllers\Vendor;

use App\Modules\AfterSales\Http\Requests\Vendor\StoreReturnPolicyRequest;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Return policy (VEN-079, Figma Vendor Panel 90:117144): the store's
 * override of the platform default — stricter only (shorter window, higher restocking
 * fee, more non-returnable categories, exchanges off) — and the policy text shoppers see.
 * Permissions: settings.return-policy.view (page) / settings.return-policy.manage (save).
 */
class ReturnPolicyController extends Controller
{
    public function __construct(
        private readonly ReturnPolicyService $policies,
        private readonly CategoryService $categories,
    ) {}

    public function edit(Request $request): View
    {
        $staff = $request->user('vendor');
        $override = $this->policies->storeOverride();

        return view('after-sales::vendor.settings.return-policy', [
            'platform' => $this->policies->platformPolicy(),
            'policy' => $this->policies->policy(),
            'text' => $override['text'] ?? [],
            'customised' => $override !== null,
            'categories' => $this->categories->parentOptions(),
            'canManage' => $staff instanceof VendorStaff && $staff->can('settings.return-policy.manage'),
        ]);
    }

    public function update(StoreReturnPolicyRequest $request): JsonResponse
    {
        $this->policies->saveStoreOverride($request->toData());

        return JsonResponder::success(__('after-sales::vendor_policy.saved'), redirect: vendor_route('settings.return-policy'));
    }
}
