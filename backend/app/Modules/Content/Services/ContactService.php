<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\DTOs\ContactMessageData;
use App\Modules\Content\Events\ContactMessageReceived;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Content\Repositories\Contracts\ContactMessageRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Central contact form (WEB-008): stores the message, then ContactMessageReceived
 * notifies admins and mails the support inbox (SendContactNotifications).
 */
class ContactService extends BaseService
{
    public function __construct(private readonly ContactMessageRepositoryContract $messages) {}

    public function submit(ContactMessageData $data, ?string $ip, ?string $userAgent): ContactMessage
    {
        /** @var ContactMessage $message */
        $message = $this->messages->create([
            'name' => $data->name,
            'email' => mb_strtolower($data->email),
            'phone' => $data->phone,
            'subject' => $data->subject,
            'message' => $data->message,
            'locale' => app()->getLocale(),
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 250, '') : null,
        ]);

        $this->dispatchAfterCommit(new ContactMessageReceived($message));

        return $message;
    }
}
