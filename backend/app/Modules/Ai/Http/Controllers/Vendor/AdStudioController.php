<?php

namespace App\Modules\Ai\Http\Controllers\Vendor;

use App\Modules\Ai\DataTables\VideoAdDataTable;
use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Http\Requests\Vendor\VideoAdRequest;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Services\VideoAds\VideoAdStudioService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * VEN-010c AI Advertising Studio (Figma 12:15119, 4-step video ad wizard) — Ads &
 * Marketing › AI Advertising Studio: the Advertising Library page + table, the product
 * picker, Generate (429 = monthly `ai_ads` quota reached, 503 = OpenAI not configured),
 * live status of one ad, Try again, Delete and Download.
 * Permissions: marketing.ads.view (page, table, status, download) /
 * marketing.ads.manage (products, generate, retry, delete). Tenant DB only.
 */
class AdStudioController extends Controller
{
    public function __construct(private readonly VideoAdStudioService $studio) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId();
        $quota = $this->studio->quota($tenantId);
        $requested = filter_var($request->query('ad'), FILTER_VALIDATE_INT);

        return view('ai::vendor.ad-studio.index', [
            'canManage' => $this->canManage($request),
            'configured' => OpenAiTools::configured(),
            'quota' => $quota,
            'active' => $this->studio->activeCount(),
            'platforms' => VideoAdPlatform::options(),
            'statuses' => [
                VideoAdStatus::Queued->value => (string) __('ai::ad_studio.library.in_progress'),
                VideoAdStatus::Ready->value => VideoAdStatus::Ready->label(),
                VideoAdStatus::Failed->value => VideoAdStatus::Failed->label(),
            ],
            'openAd' => is_int($requested) ? $requested : null,
            'startProduct' => $this->studio->productCard(filter_var($request->query('product'), FILTER_VALIDATE_INT) ?: null),
        ]);
    }

    public function data(Request $request, VideoAdDataTable $table): JsonResponse
    {
        return $table->forStaff($this->canManage($request))->toJsonFromRequest($request, VideoAdDataTable::FILTERS);
    }

    public function products(Request $request): JsonResponse
    {
        $term = $request->query('q');

        return response()->json($this->studio->searchProducts(is_string($term) ? mb_substr($term, 0, 100) : null, (int) $request->query('page', '1')));
    }

    public function store(VideoAdRequest $request): JsonResponse
    {
        try {
            $ad = $this->studio->create($request->toData(), $request->user('vendor')?->getKey(), $this->tenantId());
        } catch (AiQuotaExceededException $exception) {
            return JsonResponder::error($exception->getMessage(), 429, ['quota' => $exception->status->toClient()]);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), OpenAiTools::configured() ? 422 : 503);
        }

        return JsonResponder::success((string) __('ai::ad_studio.queued'), [
            'ad' => $this->studio->present($ad->refresh(), true),
            'quota' => $this->studio->quota($this->tenantId())->toClient(),
        ]);
    }

    public function show(Request $request, int $ad): JsonResponse
    {
        return JsonResponder::success(data: ['ad' => $this->studio->present($this->studio->find($ad), $this->canManage($request))]);
    }

    public function retry(Request $request, int $ad): JsonResponse
    {
        try {
            $current = $this->studio->retry($this->studio->find($ad), $this->tenantId());
        } catch (AiQuotaExceededException $exception) {
            return JsonResponder::error($exception->getMessage(), 429, ['quota' => $exception->status->toClient()]);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('ai::ad_studio.queued'), ['ad' => $this->studio->present($current->refresh(), true)]);
    }

    public function destroy(int $ad): JsonResponse
    {
        $current = $this->studio->find($ad);

        if ($current->status->isActive()) {
            return JsonResponder::error((string) __('ai::ad_studio.errors.busy'), 422);
        }

        $this->studio->delete($current);

        return JsonResponder::success((string) __('ai::ad_studio.deleted'));
    }

    public function download(int $ad): BinaryFileResponse|JsonResponse
    {
        try {
            $file = $this->studio->download($this->studio->find($ad));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return response()->download($file['path'], $file['name'])->deleteFileAfterSend($file['temporary']);
    }

    private function canManage(Request $request): bool
    {
        return $request->user('vendor')?->can('marketing.ads.manage') ?? false;
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }
}
