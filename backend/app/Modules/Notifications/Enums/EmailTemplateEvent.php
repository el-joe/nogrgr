<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Every e-mail the platform can send with an admin-editable template. Each event
 * declares its audience, the `{{ variables }}` it provides and sample values for the
 * live preview / "Send test".
 */
enum EmailTemplateEvent: string
{
    use HasEnumHelpers;

    case VendorWelcome = 'vendor.welcome';
    case VendorRegistrationLink = 'vendor.registration_link';
    case VendorInvoiceIssued = 'vendor.invoice_issued';
    case VendorInvoicePaid = 'vendor.invoice_paid';
    case VendorPaymentFailed = 'vendor.payment_failed';
    case VendorPaymentRefunded = 'vendor.payment_refunded';
    case VendorSubscriptionActivated = 'vendor.subscription_activated';
    case VendorSubscriptionPastDue = 'vendor.subscription_past_due';
    case VendorSubscriptionExpired = 'vendor.subscription_expired';
    case OrderConfirmation = 'order.confirmation';
    case OrderShipped = 'order.shipped';
    case OrderDelivered = 'order.delivered';
    case OrderCancelled = 'order.cancelled';
    case OrderRefundProcessed = 'order.refund_processed';
    case CustomerPasswordReset = 'customer.password_reset';
    case AffiliateWelcome = 'affiliate.welcome';
    case AffiliatePayoutSent = 'affiliate.payout_sent';
    case AdminNewVendor = 'admin.new_vendor';
    case AdminPaymentFailed = 'admin.payment_failed';

    public function audience(): EmailAudience
    {
        return match (strtok($this->value, '.')) {
            'vendor' => EmailAudience::Vendors,
            'order', 'customer' => EmailAudience::Shoppers,
            'affiliate' => EmailAudience::Affiliates,
            default => EmailAudience::Admins,
        };
    }

    /**
     * Variable name => sample value (preview and test sends).
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        $platform = ['platform.name' => (string) config('app.name')];
        $store = ['store.name' => 'Megz Store', 'store.url' => 'https://megz.example.com'];
        $vendor = ['vendor.name' => 'Mona Khaled', ...$store];
        $customer = ['customer.first_name' => 'Ahmed', 'customer.name' => 'Ahmed Ali', ...$store];
        $order = ['order.number' => 'ORD-10431', 'order.total' => '$129.00', 'order.url' => 'https://megz.example.com/account/orders/10431'];
        $invoice = ['invoice.number' => 'INV-2026-1042', 'invoice.total' => '$49.00', 'invoice.due_date' => '2026-12-01', 'invoice.url' => 'https://nogrgr.example.com/tenant/megz/billing'];
        $subscription = ['plan.name' => 'Growth', 'subscription.ends_at' => '2026-12-25'];

        return $platform + match ($this) {
            self::VendorWelcome => $vendor + ['login.url' => 'https://nogrgr.example.com/tenant/login'],
            self::VendorRegistrationLink => ['vendor.name' => 'Mona Khaled', 'registration.url' => 'https://nogrgr.example.com/register/complete'],
            self::VendorInvoiceIssued, self::VendorInvoicePaid => $vendor + $invoice,
            self::VendorPaymentFailed, self::VendorPaymentRefunded => $vendor + $invoice + ['payment.amount' => '$49.00', 'payment.gateway' => 'Stripe'],
            self::VendorSubscriptionActivated, self::VendorSubscriptionPastDue, self::VendorSubscriptionExpired => $vendor + $subscription,
            self::OrderConfirmation, self::OrderDelivered, self::OrderCancelled => $customer + $order,
            self::OrderShipped => $customer + $order + ['shipment.company' => 'Aramex', 'shipment.tracking_no' => '3482 9910 22', 'shipment.tracking_url' => 'https://www.aramex.com/track'],
            self::OrderRefundProcessed => $customer + $order + ['refund.amount' => '$29.00'],
            self::CustomerPasswordReset => $customer + ['reset.url' => 'https://megz.example.com/reset-password'],
            self::AffiliateWelcome => ['affiliate.name' => 'Sara Nabil', 'login.url' => 'https://nogrgr.example.com/affiliate/login'],
            self::AffiliatePayoutSent => ['affiliate.name' => 'Sara Nabil', 'payout.amount' => '$310.00', 'payout.reference' => 'PO-2026-118'],
            self::AdminNewVendor => $store + ['vendor.name' => 'Mona Khaled', 'admin.url' => 'https://nogrgr.example.com/admin/vendors'],
            self::AdminPaymentFailed => $store + $invoice + ['payment.gateway' => 'Stripe'],
        };
    }

    /**
     * @return list<self>
     */
    public static function forAudience(EmailAudience $audience): array
    {
        return array_values(array_filter(self::cases(), static fn (self $event): bool => $event->audience() === $audience));
    }
}
