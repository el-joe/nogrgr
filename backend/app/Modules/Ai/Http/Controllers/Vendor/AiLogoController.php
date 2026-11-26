<?php

namespace App\Modules\Ai\Http\Controllers\Vendor;

use App\Modules\Ai\Enums\LogoColor;
use App\Modules\Ai\Enums\LogoStyle;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Http\Requests\Vendor\AiLogoRequest;
use App\Modules\Ai\Services\Logo\LogoGeneratorService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * VEN-034a Appearance › Logo › AI logo generator (store.appearance.manage): generate
 * four options (429 = monthly AI logo quota reached, 503 = provider problem), then use
 * one as the store logo.
 */
class AiLogoController extends Controller
{
    public function __construct(private readonly LogoGeneratorService $logos) {}

    public function generate(AiLogoRequest $request): JsonResponse
    {
        $tenantId = (int) tenant()?->getTenantKey();

        try {
            $result = $this->logos->generate(
                (string) $request->validated('store_name'),
                $request->validated('tagline') !== null ? (string) $request->validated('tagline') : null,
                LogoStyle::from((string) $request->validated('style')),
                LogoColor::from((string) $request->validated('color')),
                $tenantId,
            );
        } catch (AiQuotaExceededException $exception) {
            return JsonResponder::error($exception->getMessage(), 429, ['quota' => $exception->status->toClient()]);
        } catch (AiRequestException $exception) {
            return JsonResponder::error($exception->getMessage(), 503);
        }

        $quota = $this->logos->quota($tenantId);

        return JsonResponder::success(data: $result + ['quota' => $quota->toClient(), 'used_label' => self::usedLabel($quota->used, $quota->max)]);
    }

    public function apply(AiLogoRequest $request): JsonResponse
    {
        try {
            $url = $this->logos->apply((string) $request->validated('option'));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('ai::tools.logo.saved'), ['url' => $url]);
    }

    public static function usedLabel(int $used, ?int $max): string
    {
        return $max === null ? (string) __('ai::tools.logo.unlimited') : (string) __('ai::tools.logo.used', ['used' => $used, 'max' => $max]);
    }
}
