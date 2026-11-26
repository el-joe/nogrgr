<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Subject of a storefront support request (Figma SF-ECO-035 select). Customer-facing
 * labels: static texts support.subjects.<value>.
 */
enum SupportSubject: string
{
    use HasEnumHelpers;

    case OrderIssue = 'order_issue';
    case Delivery = 'delivery';
    case ReturnRefund = 'return_refund';
    case Payment = 'payment';
    case ProductQuestion = 'product_question';
    case Account = 'account';
    case Other = 'other';

    /**
     * @return array<string, string> value => storefront label
     */
    public static function storefrontOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = __st('support.subjects.'.$case->value);
        }

        return $options;
    }
}
