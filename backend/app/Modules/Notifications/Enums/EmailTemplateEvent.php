<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Code-driven registry of every e-mail the platform sends (decision #118). Each key
 * declares its audience, whether a store may override it (VEN-080), the `{{ variables }}`
 * it provides (name => sample value; descriptions in `notifications::mail_variables`)
 * and its default subject / body per locale (`notifications::mail_defaults`). Admin rows
 * (`email_templates`) and store rows (`store_email_templates`) only override the default.
 * Every Notification that sends mail declares its keys (TemplatedMailNotification);
 * tests/Unit/Architecture/EmailTemplateRegistryTest.php enforces it.
 */
enum EmailTemplateEvent: string
{
    use HasEnumHelpers;

    case VendorWelcome = 'vendor.welcome';
    case VendorRegistrationLink = 'vendor.registration_link';
    case VendorRegistrationReceipt = 'vendor.registration_receipt';
    case VendorInvoiceIssued = 'vendor.invoice_issued';
    case VendorInvoicePaid = 'vendor.invoice_paid';
    case VendorPaymentFailed = 'vendor.payment_failed';
    case VendorPaymentRefunded = 'vendor.payment_refunded';
    case VendorSubscriptionActivated = 'vendor.subscription_activated';
    case VendorSubscriptionPastDue = 'vendor.subscription_past_due';
    case VendorSubscriptionExpired = 'vendor.subscription_expired';
    case VendorComplianceDecision = 'vendor.compliance_decision';
    case VendorVerifyEmail = 'vendor.verify_email';
    case VendorPasswordReset = 'vendor.password_reset';
    case VendorStaffInvitation = 'vendor.staff_invitation';
    case VendorAlert = 'vendor.alert';
    case VendorNewOrder = 'vendor.new_order';
    case VendorOrderCancelled = 'vendor.order_cancelled';
    case VendorReturnUpdate = 'vendor.return_update';
    case VendorRefundUpdate = 'vendor.refund_update';
    case VendorPayoutUpdate = 'vendor.payout_update';
    case VendorReviewUpdate = 'vendor.review_update';
    case VendorContactMessage = 'vendor.contact_message';
    case VendorRequestUpdate = 'vendor.request_update';
    case VendorThemeUpdate = 'vendor.theme_update';
    case VendorTeamUpdate = 'vendor.team_update';
    case VendorSubscriptionCancelled = 'vendor.subscription_cancelled';
    case VendorSubscriptionRenewed = 'vendor.subscription_renewed';
    case VendorInvoiceVoided = 'vendor.invoice_voided';
    case VendorDomainConnected = 'vendor.domain_connected';
    case VendorDomainDnsFailed = 'vendor.domain_dns_failed';
    case VendorLowStock = 'vendor.low_stock';
    case VendorSettlementDue = 'vendor.settlement_due';
    case VendorSettlementReceipt = 'vendor.settlement_receipt';
    case VendorGatewayLimit = 'vendor.gateway_limit';
    case VendorSubscriptionRenewalReminder = 'vendor.subscription_renewal_reminder';
    case OrderConfirmation = 'order.confirmation';
    case OrderShipped = 'order.shipped';
    case OrderDelivered = 'order.delivered';
    case OrderCancelled = 'order.cancelled';
    case OrderRefundProcessed = 'order.refund_processed';
    case OrderUpdate = 'order.update';
    case OrderShippingUpdate = 'order.shipping_update';
    case OrderReturnUpdate = 'order.return_update';
    case CustomerPasswordReset = 'customer.password_reset';
    case CustomerPasswordResetCode = 'customer.password_reset_code';
    case CustomerEmailCode = 'customer.email_code';
    case CustomerTicketUpdate = 'customer.ticket_update';
    case CustomerWelcome = 'customer.welcome';
    case CustomerBackInStock = 'customer.back_in_stock';
    case CustomerReviewPublished = 'customer.review_published';
    case AffiliateWelcome = 'affiliate.welcome';
    case AffiliatePayoutSent = 'affiliate.payout_sent';
    case AffiliateActivated = 'affiliate.activated';
    case AffiliateConversion = 'affiliate.conversion';
    case AffiliatePayout = 'affiliate.payout';
    case AffiliateVerifyEmail = 'affiliate.verify_email';
    case AffiliatePasswordReset = 'affiliate.password_reset';
    case AffiliateAlert = 'affiliate.alert';
    case AdminNewVendor = 'admin.new_vendor';
    case AdminPaymentFailed = 'admin.payment_failed';
    case AdminPasswordReset = 'admin.password_reset';
    case AdminContactMessage = 'admin.contact_message';
    case AdminAlert = 'admin.alert';
    case AdminWelcome = 'admin.welcome';
    case AdminPasswordChanged = 'admin.password_changed';
    case AdminTeamUpdate = 'admin.team_update';
    case AdminOrderAlert = 'admin.order_alert';
    case AdminReturnAlert = 'admin.return_alert';
    case AdminRequestAlert = 'admin.request_alert';
    case AdminBillingAlert = 'admin.billing_alert';
    case AdminVendorAlert = 'admin.vendor_alert';
    case AdminShippingEscalation = 'admin.shipping_escalation';
    case AdminGatewayLimitWarning = 'admin.gateway_limit_warning';
    case AdminGatewayLimitBlock = 'admin.gateway_limit_block';
    case AdminAffiliatePayoutRequest = 'admin.affiliate_payout_request';
    case SupportTicketReply = 'support.ticket_reply';
    case CentralNewsletterConfirmation = 'central.newsletter_confirmation';

    public function audience(): EmailAudience
    {
        return match (strtok($this->value, '.')) {
            'vendor', 'support', 'central' => EmailAudience::Vendors,
            'order', 'customer' => EmailAudience::Shoppers,
            'affiliate' => EmailAudience::Affiliates,
            default => EmailAudience::Admins,
        };
    }

    /**
     * Sent from inside a store (tenant context) and overridable per store in Vendor ›
     * Email templates; the store layout (logo, colour, social links) wraps it.
     */
    public function isStoreScoped(): bool
    {
        return $this->audience() === EmailAudience::Shoppers
            || in_array($this, [
                self::VendorAlert, self::VendorStaffInvitation, self::VendorNewOrder, self::VendorOrderCancelled, self::VendorReturnUpdate,
                self::VendorRefundUpdate, self::VendorPayoutUpdate, self::VendorReviewUpdate, self::VendorContactMessage, self::VendorTeamUpdate,
                self::VendorLowStock, self::VendorSettlementDue, self::VendorSettlementReceipt, self::VendorGatewayLimit,
            ], true);
    }

    /**
     * Account / security e-mails a flow depends on (reset links, codes, verification,
     * invitations, password-changed alert): always sent, the enable switch is locked on.
     */
    public function isMandatory(): bool
    {
        return in_array($this, [
            self::VendorRegistrationLink, self::VendorVerifyEmail, self::VendorPasswordReset, self::VendorStaffInvitation,
            self::CustomerPasswordReset, self::CustomerPasswordResetCode, self::CustomerEmailCode,
            self::AffiliateVerifyEmail, self::AffiliatePasswordReset, self::AdminPasswordReset, self::AdminPasswordChanged,
            self::CentralNewsletterConfirmation,
        ], true);
    }

    /**
     * @return list<self>
     */
    public static function storeScoped(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $event): bool => $event->isStoreScoped()));
    }

    /**
     * Variable name => sample value (preview and test sends). The names are the
     * documented contract; descriptions live in `notifications::mail_variables`.
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
        $recipient = ['recipient.name' => 'Mohamed'];
        $alert = $recipient + ['alert.title' => 'New order #ORD-10431', 'alert.body' => 'A new order of $129.00 was placed in your store.', 'action.url' => 'https://nogrgr.example.com/admin/orders/10431'];
        $reset = $recipient + ['reset.url' => 'https://nogrgr.example.com/reset-password/abc', 'reset.expires_minutes' => '60'];
        $verify = $recipient + ['verify.url' => 'https://nogrgr.example.com/verify/abc', 'verify.expires_minutes' => '60'];
        $code = $customer + ['code.value' => '482913', 'code.expires_minutes' => '10'];

        return $platform + match ($this) {
            self::VendorWelcome => $vendor + ['login.url' => 'https://nogrgr.example.com/tenant/login'],
            self::VendorRegistrationLink => ['vendor.name' => 'Mona Khaled', 'registration.url' => 'https://nogrgr.example.com/register/complete', 'registration.expires_hours' => '48'],
            self::VendorRegistrationReceipt => ['plan.name' => 'Growth', 'plan.term' => 'Yearly', 'payment.amount' => '$490.00', 'payment.reference' => 'REG-2026-0042'],
            self::VendorInvoiceIssued, self::VendorInvoicePaid => $vendor + $invoice,
            self::VendorPaymentFailed, self::VendorPaymentRefunded => $vendor + $invoice + ['payment.amount' => '$49.00', 'payment.gateway' => 'Stripe'],
            self::VendorSubscriptionActivated, self::VendorSubscriptionPastDue, self::VendorSubscriptionExpired => $vendor + $subscription,
            self::VendorComplianceDecision => $store + ['compliance.status' => 'Approved', 'compliance.message' => 'Your store documents were reviewed and approved.', 'compliance.note' => 'Thanks for the quick reply.'],
            self::VendorVerifyEmail, self::AffiliateVerifyEmail => $verify,
            self::VendorPasswordReset, self::AdminPasswordReset, self::AffiliatePasswordReset => $reset,
            self::VendorStaffInvitation => $store + ['inviter.name' => 'Mona Khaled', 'role.name' => 'Store manager', 'invitation.url' => 'https://megz.example.com/admin/invitation/abc', 'invitation.expires_minutes' => '4320'],
            self::VendorAlert, self::VendorNewOrder, self::VendorOrderCancelled, self::VendorReturnUpdate, self::VendorRefundUpdate,
            self::VendorPayoutUpdate, self::VendorReviewUpdate, self::VendorContactMessage, self::VendorRequestUpdate, self::VendorThemeUpdate,
            self::VendorTeamUpdate, self::VendorGatewayLimit => $alert + $store,
            self::VendorLowStock => $alert + $store + ['product.name' => 'Linen summer shirt', 'product.stock' => '3', 'product.threshold' => '5'],
            self::VendorSettlementDue => $alert + $store + ['settlement.amount' => '$86.40'],
            self::VendorSettlementReceipt => $alert + $store + ['settlement.amount' => '$86.40', 'settlement.reference' => 'ST-2026-0412', 'order.number' => 'ORD-10431'],
            self::VendorSubscriptionRenewalReminder => $vendor + $subscription + ['reminder.days' => '7', 'renew.url' => 'https://nogrgr.example.com/owner/subscriptions'],
            self::VendorDomainDnsFailed => $vendor + ['domain.name' => 'shop.megz.com', 'login.url' => 'https://nogrgr.example.com/tenant/login'],
            self::AdminAlert, self::AffiliateAlert, self::AdminWelcome, self::AdminPasswordChanged, self::AdminTeamUpdate, self::AdminOrderAlert,
            self::AdminReturnAlert, self::AdminRequestAlert, self::AdminBillingAlert, self::AdminVendorAlert, self::AdminNewVendor, self::AdminPaymentFailed,
            self::AdminShippingEscalation, self::AdminGatewayLimitWarning, self::AdminGatewayLimitBlock, self::AdminAffiliatePayoutRequest => $alert,
            self::VendorSubscriptionCancelled, self::VendorSubscriptionRenewed => $vendor + $subscription,
            self::VendorInvoiceVoided => $vendor + $invoice,
            self::VendorDomainConnected => $vendor + ['domain.name' => 'shop.megz.com', 'login.url' => 'https://nogrgr.example.com/tenant/login'],
            self::CustomerWelcome => $customer,
            self::OrderConfirmation, self::OrderCancelled => $customer + $order,
            self::OrderDelivered => $customer + $order + ['order.review_url' => 'https://megz.example.com/orders/10431?review=1'],
            self::OrderShipped => $customer + $order + ['shipment.company' => 'Aramex', 'shipment.tracking_no' => '3482 9910 22', 'shipment.tracking_url' => 'https://www.aramex.com/track'],
            self::OrderRefundProcessed => $customer + $order + ['refund.amount' => '$29.00'],
            self::OrderUpdate => $customer + $order + ['alert.title' => 'Order ORD-10431 is out for delivery', 'alert.body' => 'Your order ORD-10431 is out for delivery with Aramex today.'],
            self::OrderShippingUpdate => $customer + $order + ['alert.title' => 'Order ORD-10431 is out for delivery', 'alert.body' => 'Your order ORD-10431 is out for delivery with Aramex today.', 'shipment.company' => 'Aramex', 'shipment.tracking_no' => '3482 9910 22'],
            self::OrderReturnUpdate => $customer + $order + ['alert.title' => 'Return R-2041 approved', 'alert.body' => 'Your return R-2041 was approved. Please hand the parcel to the courier.', 'return.reference' => 'R-2041', 'return.status' => 'Approved', 'return.message' => ''],
            self::CustomerBackInStock => $customer + ['product.name' => 'Linen summer shirt', 'product.url' => 'https://megz.example.com/products/42/linen-summer-shirt'],
            self::CustomerReviewPublished => $customer + ['product.name' => 'Linen summer shirt', 'product.url' => 'https://megz.example.com/products/42/linen-summer-shirt', 'review.rating' => '5'],
            self::CustomerPasswordReset => $customer + ['reset.url' => 'https://megz.example.com/reset-password', 'reset.expires_minutes' => '60'],
            self::CustomerPasswordResetCode, self::CustomerEmailCode => $code,
            self::CustomerTicketUpdate => $customer + ['ticket.reference' => 'C-1042', 'ticket.subject' => 'Where is my order?', 'alert.title' => 'New reply on C-1042', 'alert.body' => 'The store replied to your ticket “Where is my order?”.', 'reply.excerpt' => 'Your parcel left our warehouse today.', 'ticket.url' => 'https://megz.example.com/account/support/1042'],
            self::AffiliateWelcome => ['affiliate.name' => 'Sara Nabil', 'login.url' => 'https://nogrgr.example.com/affiliate/login'],
            self::AffiliatePayoutSent => ['affiliate.name' => 'Sara Nabil', 'payout.amount' => '$310.00', 'payout.reference' => 'PO-2026-118'],
            self::AffiliateActivated => ['affiliate.name' => 'Sara Nabil', 'affiliate.code' => 'SARA2026', 'referral.url' => 'https://nogrgr.example.com/?ref=SARA2026', 'login.url' => 'https://nogrgr.example.com/affiliate/login'],
            self::AffiliateConversion => ['affiliate.name' => 'Sara Nabil', 'conversion.status' => 'Approved', 'store.name' => 'Megz Store', 'plan.name' => 'Growth', 'commission.amount' => '$237.60', 'conversion.note' => '', 'dashboard.url' => 'https://nogrgr.example.com/affiliate'],
            self::AffiliatePayout => ['affiliate.name' => 'Sara Nabil', 'payout.status' => 'Paid', 'payout.amount' => '$310.00', 'payout.reference' => 'PP-7781-22', 'payout.method' => 'Bank transfer', 'payout.note' => '', 'dashboard.url' => 'https://nogrgr.example.com/affiliate'],
            self::AdminContactMessage => ['contact.name' => 'Omar Hassan', 'contact.email' => 'omar@example.com', 'contact.phone' => '+20 100 000 0000', 'contact.subject' => 'Pricing question', 'contact.message' => 'Do you offer a discount for yearly plans?'],
            self::SupportTicketReply => $store + ['recipient.name' => 'Mohamed', 'ticket.reference' => 'T-4471', 'ticket.subject' => 'Shipping zone not saving', 'reply.author' => 'Laila, Nogrgr support', 'reply.excerpt' => 'We fixed the shipping zone sync. Please try saving the zone again and tell us if it works.', 'ticket.url' => 'https://nogrgr.example.com/tenant/megz/admin/support/4471'],
            self::CentralNewsletterConfirmation => ['confirm.url' => 'https://nogrgr.example.com/newsletter/confirm/abc', 'unsubscribe.url' => 'https://nogrgr.example.com/newsletter/unsubscribe/abc'],
        };
    }

    /**
     * Documented variables: name => [description, sample].
     *
     * @return array<string, array{description: string, sample: string}>
     */
    public function variableDefinitions(?string $locale = null): array
    {
        $definitions = [];

        foreach ($this->variables() as $name => $sample) {
            $definitions[$name] = ['description' => self::describeVariable($name, $locale), 'sample' => $sample];
        }

        return $definitions;
    }

    public static function describeVariable(string $name, ?string $locale = null): string
    {
        $key = 'notifications::mail_variables.'.$name;
        $text = __($key, [], $locale);

        return is_string($text) && $text !== $key ? $text : $name;
    }

    public function defaultSubject(string $locale): string
    {
        return $this->defaultText('subject', $locale);
    }

    public function defaultBody(string $locale): string
    {
        return $this->defaultText('body', $locale);
    }

    /**
     * Whether the code registry ships a default for the locale (arch test).
     */
    public function hasDefault(string $locale): bool
    {
        $key = 'notifications::mail_defaults.'.$this->value.'.subject';

        return __($key, [], $locale) !== $key && trim($this->defaultBody($locale)) !== '';
    }

    private function defaultText(string $field, string $locale): string
    {
        $key = 'notifications::mail_defaults.'.$this->value.'.'.$field;
        $text = app('translator')->get($key, [], $locale, false);

        if (! is_string($text) || $text === $key) {
            $text = app('translator')->get($key, [], 'en', false);
        }

        return is_string($text) && $text !== $key ? $text : '';
    }

    /**
     * @return list<self>
     */
    public static function forAudience(EmailAudience $audience): array
    {
        return array_values(array_filter(self::cases(), static fn (self $event): bool => $event->audience() === $audience));
    }
}
