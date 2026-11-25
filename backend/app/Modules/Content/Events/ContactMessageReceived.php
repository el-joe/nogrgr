<?php

namespace App\Modules\Content\Events;

use App\Modules\Content\Models\ContactMessage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A visitor sent the central contact form (WEB-008).
 */
final class ContactMessageReceived
{
    use Dispatchable;

    public function __construct(public readonly ContactMessage $message) {}
}
