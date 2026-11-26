<?php

namespace App\Modules\Localization\Http\Controllers\Vendor;

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
use App\Modules\Localization\DataTables\StoreLanguagePurchasesDataTable;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Http\Concerns\AnswersDomainErrors;
use App\Modules\Localization\Http\Requests\Vendor\StoreLanguagePurchaseRequest;
use App\Modules\Localization\Http\Requests\Vendor\UpdateStoreLanguagesRequest;
use App\Modules\Localization\Services\StoreLanguageService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Languages (VEN-073 / 073a / 073b, Figma Vendor Panel (new screens)
 * 90:113194): "Languages" tab (the store's languages with default / on / order and the
 * paid translation status of each bought one, languages available to buy and the
 * checkout card) and "Purchases history" tab (server-side table).
 * Permissions: settings.languages.view (pages) / settings.languages.manage (save, buy, retry).
 */
class StoreLanguageController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(private readonly StoreLanguageService $languages) {}

    public function index(Request $request, InvoiceCheckoutService $checkout): View
    {
        $tenant = $this->tenant();
        $tab = $request->query('tab') === 'history' ? 'history' : 'languages';
        $purchasable = $this->languages->purchasable($tenant);
        $buyId = (int) $request->query('buy', 0);

        return view('localization::vendor.languages.index', [
            'tab' => $tab,
            'store' => $this->languages->storeLanguages($tenant),
            'purchasable' => $purchasable,
            'buying' => $purchasable->first(static fn (array $row): bool => $row['language']->id === $buyId) ?? ($buyId === 0 ? $purchasable->first() : null),
            'gateways' => $checkout->gateways(),
            'purpose' => GatewayPurpose::VendorPayments,
            'canManage' => $this->canManage($request),
            'canSeeInvoices' => $this->staff($request)?->can('finance.invoices.view') ?? false,
            'live' => StoreTranslationLive::config((int) $tenant->id, vendor_route('settings.languages.runs')),
        ]);
    }

    /**
     * Live status of the store's translation runs (re-read on every Reverb push and
     * polled while one is active): rendered status block + badge per bought language.
     */
    public function runs(Request $request): JsonResponse
    {
        $canManage = $this->canManage($request);
        $runs = [];
        $active = false;

        foreach ($this->languages->storeLanguages($this->tenant()) as $row) {
            $run = $row['run'];

            if ($run === null || $row['row'] !== null) {
                continue;
            }

            $name = $row['language']?->name ?: strtoupper($row['code']);
            $active = $active || $run->status->isActive();
            $runs[$row['code']] = [
                'status' => $run->status->value,
                'progress' => $run->progress,
                'html' => view('localization::vendor.languages._run', ['run' => $run, 'name' => $name, 'canManage' => $canManage])->render(),
                'badge' => view('localization::vendor.languages._run-badge', ['run' => $run])->render(),
            ];
        }

        return JsonResponder::success(data: ['active' => $active, 'runs' => (object) $runs]);
    }

    public function history(Request $request, StoreLanguagePurchasesDataTable $table): JsonResponse
    {
        return $table->forTenant((int) $this->tenant()->id, $this->staff($request)?->can('finance.invoices.view') ?? false)
            ->toJsonFromRequest($request, StoreLanguagePurchasesDataTable::FILTERS);
    }

    public function update(UpdateStoreLanguagesRequest $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->languages->save($this->tenant(), $request->toData()),
            __('localization::vendor_languages.saved'),
            vendor_route('settings.languages.index'),
        );
    }

    /**
     * Form post → hosted checkout; the inline Stripe card posts as JSON (decision #78 b).
     */
    public function purchase(StoreLanguagePurchaseRequest $request, int $language, PaymentDriverRegistry $drivers): RedirectResponse|JsonResponse
    {
        $tenant = $this->tenant();
        $inline = $request->expectsJson();
        $start = fn (): array => $this->languages->startPurchase($tenant, $this->languages->findPurchasable($tenant, $language), $request->gateway(), $this->staff($request)?->email);

        try {
            $started = $inline ? $drivers->inline($start) : $start();
        } catch (PaymentGatewayException|TranslationRuleException|BillingRuleException $exception) {
            return $request->expectsJson()
                ? JsonResponder::error($exception->getMessage(), 422)
                : redirect()->to(vendor_route('settings.languages.index'))->with('error', $exception->getMessage());
        }

        return $inline ? PaymentStartResponse::json($started) : redirect()->away($started['redirect']);
    }

    public function paymentResult(int $payment): RedirectResponse
    {
        $log = $this->log($payment);

        if ($log->status === PaymentLogStatus::Pending) {
            try {
                $log = $this->languages->confirmPayment($log);
            } catch (PaymentGatewayException) {
                // The webhook settles it later.
            }
        }

        return $this->result($log, false);
    }

    public function paymentCancel(int $payment): RedirectResponse
    {
        return $this->result($this->languages->cancelPayment($this->log($payment)), true);
    }

    public function retry(string $code): JsonResponse
    {
        return $this->attempt(
            fn () => $this->languages->retry($this->tenant(), $code),
            __('localization::vendor_languages.retried'),
            vendor_route('settings.languages.index'),
        );
    }

    private function result(PaymentLog $log, bool $cancelled): RedirectResponse
    {
        $name = $this->languages->languageName((int) (($log->payload ?? [])['language_id'] ?? 0));
        [$type, $key] = match ($log->status) {
            PaymentLogStatus::Captured => ['success', 'paid'],
            PaymentLogStatus::Pending => ['info', 'processing'],
            default => ['error', $cancelled ? 'cancelled' : 'failed'],
        };

        return redirect()->to(vendor_route('settings.languages.index'))->with($type, __('localization::vendor_languages.result.'.$key, ['language' => $name]));
    }

    private function log(int $payment): PaymentLog
    {
        $log = $this->languages->paymentLog($this->tenant(), $payment);
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
