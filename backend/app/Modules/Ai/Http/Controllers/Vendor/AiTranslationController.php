<?php

namespace App\Modules\Ai\Http\Controllers\Vendor;

use App\Modules\Ai\DataTables\StoreTranslationHistoryDataTable;
use App\Modules\Ai\Http\Requests\Vendor\AiTranslationPurchaseRequest;
use App\Modules\Ai\Services\StoreTranslation\AiTranslationOverviewService;
use App\Modules\Ai\Support\StoreTranslationLive;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Billing\Support\PaymentStartResponse;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › AI translation (VEN-074 / 074a, Figma 180:28706 / 180:29374 /
 * 182:29054, decision #99): run KPIs, live per-section progress of the current run,
 * languages card + "Pay & translate" (shared language purchase / checkout, USD), run
 * history (server-side table) with Run again / Retry.
 * Permissions: settings.languages.view (page, status, history) /
 * settings.languages.manage (buy, run again, retry).
 */
class AiTranslationController extends Controller
{
    public function __construct(private readonly AiTranslationOverviewService $overview) {}

    public function index(Request $request, InvoiceCheckoutService $checkout): View
    {
        $tenant = $this->tenant();
        $purchasable = $this->overview->purchasable($tenant);
        $staff = $this->staff($request);

        return view('ai::vendor.ai-translation.index', [
            'stats' => $this->overview->stats($tenant),
            'current' => $this->overview->current($tenant),
            'languages' => $this->overview->languages($tenant),
            'purchasable' => $purchasable,
            'selected' => $purchasable->firstWhere('id', (int) $request->query('buy', 0)) ?? $purchasable->first(),
            'gateways' => $checkout->gateways(),
            'purpose' => GatewayPurpose::VendorPayments,
            'canManage' => $this->canManage($request),
            'canTranslateProducts' => $staff?->can('catalog.products.view') ?? false,
            'live' => StoreTranslationLive::config((int) $tenant->id, vendor_route('settings.ai-translation.status')),
        ]);
    }

    /**
     * Live state of the current run (re-read on every Reverb push, polled while active).
     */
    public function status(): JsonResponse
    {
        $current = $this->overview->current($this->tenant());
        $run = $current['run'];

        return JsonResponder::success(data: [
            'run' => $run?->id,
            'status' => $run?->status->value,
            'active' => $run?->status->isActive() ?? false,
            'html' => view('ai::vendor.ai-translation._current', ['current' => $current])->render(),
        ]);
    }

    public function history(Request $request, StoreTranslationHistoryDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenant(), $this->canManage($request))
            ->toJsonFromRequest($request, StoreTranslationHistoryDataTable::FILTERS);
    }

    /**
     * Form post → hosted checkout; the inline Stripe card posts as JSON (decision #78 b).
     */
    public function purchase(AiTranslationPurchaseRequest $request, PaymentDriverRegistry $drivers): RedirectResponse|JsonResponse
    {
        $tenant = $this->tenant();
        $inline = $request->expectsJson();
        $start = fn (): array => $this->overview->startPurchase($tenant, $request->languageId(), $request->gateway(), $this->staff($request)?->email);

        try {
            $started = $inline ? $drivers->inline($start) : $start();
        } catch (PaymentGatewayException|TranslationRuleException|BillingRuleException $exception) {
            return $inline
                ? JsonResponder::error($exception->getMessage(), 422)
                : redirect()->to(vendor_route('settings.ai-translation'))->with('error', $exception->getMessage());
        }

        return $inline ? PaymentStartResponse::json($started) : redirect()->away($started['redirect']);
    }

    public function paymentResult(int $payment): RedirectResponse
    {
        $log = $this->log($payment);

        if ($log->status === PaymentLogStatus::Pending) {
            try {
                $log = $this->overview->confirmPayment($log);
            } catch (PaymentGatewayException) {
                // The webhook settles it later.
            }
        }

        return $this->result($log, false);
    }

    public function paymentCancel(int $payment): RedirectResponse
    {
        return $this->result($this->overview->cancelPayment($this->log($payment)), true);
    }

    public function runAgain(int $run): JsonResponse
    {
        return $this->attempt(fn () => $this->overview->runAgain($this->tenant(), $run), (string) __('ai::ai_translation.actions.run_again_done'));
    }

    public function retry(int $run): JsonResponse
    {
        return $this->attempt(fn () => $this->overview->retry($this->tenant(), $run), (string) __('ai::ai_translation.actions.retry_done'));
    }

    private function attempt(callable $action, string $message): JsonResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success($message, redirect: vendor_route('settings.ai-translation'));
    }

    private function result(PaymentLog $log, bool $cancelled): RedirectResponse
    {
        $name = $this->overview->languageNameById((int) (($log->payload ?? [])['language_id'] ?? 0));
        $amount = $log->amount_minor !== null ? '$'.number_format($log->amount_minor / 100, 2) : '';
        [$type, $key] = match ($log->status) {
            PaymentLogStatus::Captured => ['success', 'paid'],
            PaymentLogStatus::Pending => ['info', 'processing'],
            default => ['error', $cancelled ? 'cancelled' : 'failed'],
        };

        return redirect()->to(vendor_route('settings.ai-translation'))
            ->with('ai_translation_result', ['type' => $type, 'message' => __('ai::ai_translation.result.'.$key, ['language' => $name, 'amount' => $amount])]);
    }

    private function log(int $payment): PaymentLog
    {
        $log = $this->overview->paymentLog($this->tenant(), $payment);
        abort_if($log === null, 404);

        return $log;
    }

    private function canManage(Request $request): bool
    {
        return $this->staff($request)?->can('settings.languages.manage') ?? false;
    }

    private function staff(Request $request): ?VendorStaff
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff ? $staff : null;
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
