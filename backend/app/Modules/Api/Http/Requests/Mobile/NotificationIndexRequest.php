<?php

namespace App\Modules\Api\Http\Requests\Mobile;

/**
 * Customer inbox: pages, optionally unread only.
 */
class NotificationIndexRequest extends PageRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'unread' => ['sometimes', 'boolean']];
    }
}
