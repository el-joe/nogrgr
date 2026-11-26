<?php

namespace App\Modules\Engagement\Services;

use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Engagement\DTOs\SupportRequestData;
use App\Modules\Engagement\Enums\SupportRequestStatus;
use App\Modules\Engagement\Events\SupportRequestReceived;
use App\Modules\Engagement\Models\SupportRequest;
use App\Modules\Engagement\Repositories\Contracts\SupportRequestRepositoryContract;

/**
 * Storefront support / contact requests (tenant context). The store owners are told
 * through the Notifications module (SupportRequestReceived → StoreEngagementNotification).
 */
class SupportRequestService
{
    /** Private (tenant-suffixed `local` disk) folder of the request photos. */
    public const ATTACHMENTS_DIRECTORY = 'support-requests';

    public function __construct(private readonly SupportRequestRepositoryContract $requests) {}

    public function submit(SupportRequestData $data): SupportRequest
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        $request = $this->requests->create([
            'customer_id' => $data->customerId,
            'name' => $data->name,
            'email' => mb_strtolower($data->email),
            'phone' => $data->phone,
            'order_number' => $data->orderNumber,
            'subject' => $data->subject,
            'message' => $data->message,
            'status' => SupportRequestStatus::New,
            'locale' => $data->locale,
            'ip_address' => $data->ipAddress,
        ]);

        if ($data->attachments !== []) {
            $paths = [];

            foreach ($data->attachments as $file) {
                $path = $file->store(self::ATTACHMENTS_DIRECTORY.'/'.$request->id, 'local');

                if (is_string($path)) {
                    $paths[] = $path;
                }
            }

            $this->requests->update($request, ['attachments' => $paths]);
        }

        SupportRequestReceived::dispatch($request, $tenantId);

        return $request;
    }

    public function setStatus(SupportRequest $request, SupportRequestStatus $status): SupportRequest
    {
        return $this->requests->update($request, ['status' => $status]);
    }
}
