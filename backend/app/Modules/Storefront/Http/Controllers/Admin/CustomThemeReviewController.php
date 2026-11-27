<?php

namespace App\Modules\Storefront\Http\Controllers\Admin;

use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\DataTables\CustomThemeReviewsDataTable;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Http\Requests\Admin\CustomThemeReviewRequest;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Services\CustomThemes\CustomThemePackageStore;
use App\Modules\Storefront\Services\CustomThemes\CustomThemePreviewService;
use App\Modules\Storefront\Services\CustomThemes\CustomThemeService;
use App\Modules\Storefront\Services\CustomThemes\ThemeVersionDiff;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Admin › Storefront › Theme review queue (ADM-089) and review detail (ADM-089a, Figma
 * Admin Panel 78:81175): validation report, manifest, file list + diff against the
 * store's live / previous approved version, source of each template, signed storefront
 * preview, approve / reject with a note (MAIL-034 to the store owners). Central context.
 */
class CustomThemeReviewController extends Controller
{
    public function __construct(
        private readonly CustomThemeRepositoryContract $themes,
        private readonly CustomThemeService $service,
    ) {}

    public function index(Request $request): View
    {
        $status = CustomThemeVersionStatus::tryFrom((string) $request->query('status', CustomThemeVersionStatus::PendingReview->value));

        return view('storefront::admin.theme-reviews.index', [
            'status' => $status->value ?? '',
            'statuses' => array_intersect_key(CustomThemeVersionStatus::options(), array_flip([CustomThemeVersionStatus::PendingReview->value, CustomThemeVersionStatus::Approved->value, CustomThemeVersionStatus::Rejected->value])),
            'pending' => $this->themes->reviewQuery()->where('status', CustomThemeVersionStatus::PendingReview->value)->count(),
        ]);
    }

    public function data(Request $request, CustomThemeReviewsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, CustomThemeReviewsDataTable::FILTERS);
    }

    public function show(Request $request, ThemeVersionDiff $diff, int $version): View
    {
        $model = $this->version($version);
        $base = $this->themes->previousApproved($model);

        return view('storefront::admin.theme-reviews.show', [
            'version' => $model,
            'theme' => $model->theme,
            'store' => $model->tenant,
            'diff' => $diff->compare($model, $base),
            'canManage' => (bool) $request->user('admin')?->can('storefront.theme-reviews.manage'),
        ]);
    }

    /**
     * Source of one text file of the package (templates, locales, css, js, theme.json).
     */
    public function file(Request $request, CustomThemePackageStore $store, int $version): Response
    {
        $model = $this->version($version);
        $path = (string) $request->query('path');
        abort_unless(array_key_exists($path, $model->files()) && preg_match('/\.(twig|json|css|js|svg|md|txt)$/i', $path) === 1, 404);
        $contents = $store->contents($model, true);

        return response((string) ($contents[$path] ?? ''), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"]);
    }

    public function preview(CustomThemePreviewService $previews, int $version): RedirectResponse
    {
        $model = $this->version($version);
        abort_unless(CustomThemePreviewService::previewable($model), 404);
        /** @var Tenant $tenant */
        $tenant = $model->tenant;

        return redirect()->away($previews->url($tenant, $model));
    }

    public function decide(CustomThemeReviewRequest $request, int $version): JsonResponse
    {
        $model = $this->version($version);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $request->approves()
            ? $this->service->approve($model, $admin, $request->note())
            : $this->service->reject($model, $admin, (string) $request->note());

        return JsonResponder::success(
            __($request->approves() ? 'content::custom_themes.review.approved' : 'content::custom_themes.review.rejected', ['theme' => $model->name, 'version' => $model->version]),
            redirect: route('admin.storefront.theme-reviews.show', $model->id),
        );
    }

    private function version(int $id): CustomThemeVersion
    {
        $version = $this->themes->reviewQuery()->whereKey($id)->first();
        abort_if($version === null, 404);

        return $version;
    }
}
