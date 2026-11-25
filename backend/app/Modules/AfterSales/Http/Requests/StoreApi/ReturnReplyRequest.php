<?php

namespace App\Modules\AfterSales\Http\Requests\StoreApi;

use App\Modules\AfterSales\DTOs\ReturnMessageData;
use App\Modules\AfterSales\Support\ReturnUploadLimits;

/**
 * Customer reply on a return thread (SF-ECO-032): text 10–2000, optional photos / video.
 */
class ReturnReplyRequest extends StoreApiCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['message' => ['required', 'string', 'min:10', 'max:2000']] + ReturnUploadLimits::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'message' => __('after-sales::returns.fields.reply'),
            'photos' => __('after-sales::returns.fields.photos'),
            'photos.*' => __('after-sales::returns.fields.photos'),
            'video' => __('after-sales::returns.fields.video'),
        ];
    }

    public function toData(): ReturnMessageData
    {
        return new ReturnMessageData(trim((string) $this->validated('message')), $this->uploadedPhotos(), $this->uploadedVideo());
    }
}
