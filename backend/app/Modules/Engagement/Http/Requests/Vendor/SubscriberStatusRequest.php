<?php

namespace App\Modules\Engagement\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * VEN-026 row action: `subscribed` 1 = re-subscribe, 0 = unsubscribe. Authorisation is
 * the route's `subscribers.manage` permission.
 */
class SubscriberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['subscribed' => ['required', 'boolean']];
    }

    public function subscribed(): bool
    {
        return $this->boolean('subscribed');
    }
}
