<?php

namespace App\Modules\Notifications\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\MailEncryption;
use App\Modules\Notifications\Http\Requests\Vendor\StoreMailSettingsRequest;
use App\Modules\Notifications\Services\StoreMailSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Mail (VEN-081, Figma Vendor Panel 91:115511): the store's own SMTP
 * server for its e-mails (empty = platform mail), "Send test email" with the result
 * shown on the page, "Use platform mail" to remove it.
 */
class MailSettingsController extends Controller
{
    public function __construct(private readonly StoreMailSettingsService $mail) {}

    public function index(Request $request): View
    {
        $staff = $request->user('vendor');

        return view('notifications::vendor.mail.index', [
            'settings' => $this->mail->current(),
            'encryptions' => MailEncryption::options(),
            'canManage' => $staff instanceof VendorStaff && $staff->can('settings.mail.manage'),
            'testEmail' => $staff instanceof VendorStaff ? (string) $staff->email : '',
        ]);
    }

    public function update(StoreMailSettingsRequest $request): JsonResponse
    {
        if (! $request->usesOwnServer()) {
            // RQA-VEN-012: all server fields empty = back to Nogrgr mail.
            $this->mail->clear();

            return JsonResponder::success(__('notifications::vendor_mail.cleared'), redirect: vendor_route('settings.mail'));
        }

        $this->mail->save($request->toData());

        return JsonResponder::success(__('notifications::vendor_mail.saved'), redirect: vendor_route('settings.mail'));
    }

    public function destroy(): JsonResponse
    {
        $this->mail->clear();

        return JsonResponder::success(__('notifications::vendor_mail.cleared'), redirect: vendor_route('settings.mail'));
    }

    public function test(StoreMailSettingsRequest $request): JsonResponse
    {
        $recipient = (string) $request->validated('test_email');

        try {
            $seconds = $this->mail->sendTest($request->toData(), $recipient, (string) (tenant()?->getAttribute('name') ?? config('app.name')));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422, ['delivered' => false]);
        }

        $message = __('notifications::vendor_mail.test_sent', ['email' => $recipient, 'seconds' => number_format($seconds, 1)]);

        return JsonResponder::success($message, ['delivered' => true, 'message' => $message]);
    }
}
