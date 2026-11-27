<?php

/*
| Default subject / body of every e-mail template (EmailTemplateEvent registry,
| decision #118). Admin and store templates override these; "Reset to default" restores
| them. Bodies use the editor's HTML subset; a paragraph holding only a link is drawn as
| a button, a link whose variable is empty is dropped.
*/

return [
    'vendor' => [
        'welcome' => [
            'subject' => 'Welcome to {{ platform.name }}, {{ store.name }} is ready',
            'body' => '<h2>Welcome to {{ platform.name }}, {{ vendor.name }}</h2><p>Your store {{ store.name }} is ready. Finish setup to start selling today.</p><table><tbody><tr><td>Store</td><td>{{ store.name }}</td></tr><tr><td>Address</td><td>{{ store.url }}</td></tr></tbody></table><p><a href="{{ login.url }}">Open your store panel</a></p><p>Need help? Reply to this e-mail.</p>',
        ],
        'registration_link' => [
            'subject' => 'Complete your {{ platform.name }} registration',
            'body' => '<p>Hi {{ vendor.name }},</p><p>Thanks for choosing {{ platform.name }}. Use the button below to finish setting up your store.</p><p><a href="{{ registration.url }}">Complete registration</a></p><p>The link is valid for {{ registration.expires_hours }} hours. If you did not ask for it, you can ignore this e-mail.</p>',
        ],
        'registration_receipt' => [
            'subject' => 'Payment received - {{ platform.name }}',
            'body' => '<p>Hello,</p><p>We received your payment for the <strong>{{ plan.name }}</strong> plan ({{ plan.term }}).</p><table><tbody><tr><td>Amount</td><td>{{ payment.amount }}</td></tr><tr><td>Reference</td><td>{{ payment.reference }}</td></tr></tbody></table><p>We are preparing your store now and will e-mail you as soon as it is ready.</p>',
        ],
        'invoice_issued' => [
            'subject' => 'New invoice {{ invoice.number }} for {{ store.name }}',
            'body' => '<p>Hi {{ vendor.name }},</p><p>Invoice <strong>{{ invoice.number }}</strong> of {{ invoice.total }} was issued for {{ store.name }}. It is due on {{ invoice.due_date }}.</p><p><a href="{{ invoice.url }}">View invoice</a></p>',
        ],
        'invoice_paid' => [
            'subject' => 'Invoice {{ invoice.number }} paid',
            'body' => '<p>Hi {{ vendor.name }},</p><p>Thank you! Invoice <strong>{{ invoice.number }}</strong> of {{ invoice.total }} for {{ store.name }} is paid.</p><p><a href="{{ invoice.url }}">View billing</a></p>',
        ],
        'payment_failed' => [
            'subject' => 'Payment failed for invoice {{ invoice.number }}',
            'body' => '<p>Hi {{ vendor.name }},</p><p>We could not take the payment of {{ payment.amount }} via {{ payment.gateway }} for invoice {{ invoice.number }}. Please update your payment method to keep {{ store.name }} running.</p><p><a href="{{ invoice.url }}">Update payment</a></p>',
        ],
        'payment_refunded' => [
            'subject' => 'Payment refunded for invoice {{ invoice.number }}',
            'body' => '<p>Hi {{ vendor.name }},</p><p>We refunded {{ payment.amount }} via {{ payment.gateway }} for invoice {{ invoice.number }}. It may take a few days to reach your account.</p><p><a href="{{ invoice.url }}">View billing</a></p>',
        ],
        'subscription_activated' => [
            'subject' => 'Your {{ plan.name }} plan is active',
            'body' => '<p>Hi {{ vendor.name }},</p><p>The <strong>{{ plan.name }}</strong> plan of {{ store.name }} is now active until {{ subscription.ends_at }}.</p>',
        ],
        'subscription_past_due' => [
            'subject' => 'We could not renew your {{ plan.name }} plan',
            'body' => '<p>Hi {{ vendor.name }},</p><p>The renewal of the <strong>{{ plan.name }}</strong> plan of {{ store.name }} failed. Please pay the open invoice before {{ subscription.ends_at }} to avoid interruption.</p>',
        ],
        'subscription_expired' => [
            'subject' => 'Your {{ plan.name }} plan has expired',
            'body' => '<p>Hi {{ vendor.name }},</p><p>The <strong>{{ plan.name }}</strong> plan of {{ store.name }} expired on {{ subscription.ends_at }}. Renew it to reopen your store.</p>',
        ],
        'compliance_decision' => [
            'subject' => 'Compliance review for {{ store.name }}: {{ compliance.status }}',
            'body' => '<p>Hello,</p><p>{{ compliance.message }}</p><blockquote>{{ compliance.note }}</blockquote><p>If you have questions, reply to this e-mail or open a support ticket from your panel.</p>',
        ],
        'verify_email' => [
            'subject' => 'Verify your e-mail address',
            'body' => '<p>Hi {{ recipient.name }},</p><p>Please confirm your e-mail address to finish creating your store account.</p><p><a href="{{ verify.url }}">Verify e-mail</a></p><p>The link expires in {{ verify.expires_minutes }} minutes.</p>',
        ],
        'password_reset' => [
            'subject' => 'Reset your {{ platform.name }} password',
            'body' => '<p>Hi {{ recipient.name }},</p><p>We received a request to reset the password of your store account.</p><p><a href="{{ reset.url }}">Reset password</a></p><p>The link expires in {{ reset.expires_minutes }} minutes. If you did not ask for it, ignore this e-mail.</p>',
        ],
        'staff_invitation' => [
            'subject' => 'You are invited to join {{ store.name }}',
            'body' => '<p>Hello,</p><p>{{ inviter.name }} invited you to join <strong>{{ store.name }}</strong> as {{ role.name }}.</p><p><a href="{{ invitation.url }}">Accept invitation</a></p><p>The invitation expires in {{ invitation.expires_minutes }} minutes. If you were not expecting it, ignore this e-mail.</p>',
        ],
        'alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open in your panel</a></p>',
        ],
        'new_order' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<h2>New order</h2><p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the order</a></p>',
        ],
        'order_cancelled' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the order</a></p>',
        ],
        'return_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the return</a></p>',
        ],
        'refund_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the order</a></p>',
        ],
        'payout_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open finance</a></p>',
        ],
        'review_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open reviews</a></p>',
        ],
        'contact_message' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the message</a></p>',
        ],
        'request_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the request</a></p>',
        ],
        'theme_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open your themes</a></p>',
        ],
        'team_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open your team</a></p>',
        ],
        'subscription_cancelled' => [
            'subject' => 'Your {{ plan.name }} plan was cancelled',
            'body' => '<p>Hi {{ vendor.name }},</p><p>The <strong>{{ plan.name }}</strong> plan of {{ store.name }} was cancelled. Your store stays open until {{ subscription.ends_at }}.</p><p>You can pick a plan again at any time from your billing page.</p>',
        ],
        'subscription_renewed' => [
            'subject' => 'Your {{ plan.name }} plan was renewed',
            'body' => '<p>Hi {{ vendor.name }},</p><p>The <strong>{{ plan.name }}</strong> plan of {{ store.name }} was renewed until {{ subscription.ends_at }}. Thank you!</p>',
        ],
        'invoice_voided' => [
            'subject' => 'Invoice {{ invoice.number }} was voided',
            'body' => '<p>Hi {{ vendor.name }},</p><p>Invoice <strong>{{ invoice.number }}</strong> of {{ invoice.total }} for {{ store.name }} was voided. You do not need to pay it.</p><p><a href="{{ invoice.url }}">View billing</a></p>',
        ],
        'domain_connected' => [
            'subject' => '{{ domain.name }} is connected to {{ store.name }}',
            'body' => '<h2>Your domain is live</h2><p>Hi {{ vendor.name }},</p><p>The domain <strong>{{ domain.name }}</strong> is verified and now opens {{ store.name }} with a secure certificate.</p><p><a href="{{ login.url }}">Open your store panel</a></p>',
        ],
    ],
    'order' => [
        'confirmation' => [
            'subject' => 'Order {{ order.number }} confirmed',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>Thank you for shopping at {{ store.name }}! We received your order <strong>{{ order.number }}</strong> ({{ order.total }}) and will let you know when it ships. Your invoice is available on the order page.</p><p><a href="{{ order.url }}">View your order</a></p>',
        ],
        'shipped' => [
            'subject' => 'Your order {{ order.number }} is on its way',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>Good news: order <strong>{{ order.number }}</strong> was shipped with {{ shipment.company }}. Tracking number: {{ shipment.tracking_no }}.</p><p><a href="{{ shipment.tracking_url }}">Track your parcel</a></p>',
        ],
        'delivered' => [
            'subject' => 'Your order {{ order.number }} was delivered',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>Order <strong>{{ order.number }}</strong> was delivered. We hope you love it! Tell other shoppers what you think.</p><p><a href="{{ order.review_url }}">Write a review</a></p>',
        ],
        'cancelled' => [
            'subject' => 'Order {{ order.number }} cancelled',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>Your order <strong>{{ order.number }}</strong> at {{ store.name }} was cancelled. If you paid online, the refund is on its way.</p><p><a href="{{ order.url }}">View your order</a></p>',
        ],
        'refund_processed' => [
            'subject' => 'Refund of {{ refund.amount }} processed for {{ order.number }}',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>We refunded <strong>{{ refund.amount }}</strong> for order {{ order.number }}. It may take a few days to appear on your statement.</p><p><a href="{{ order.url }}">View your order</a></p>',
        ],
        'update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>{{ alert.body }}</p><p><a href="{{ order.url }}">View your order</a></p>',
        ],
    ],
    'customer' => [
        'password_reset' => [
            'subject' => 'Reset your {{ store.name }} password',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>We received a request to reset your {{ store.name }} password.</p><p><a href="{{ reset.url }}">Reset password</a></p><p>The link expires in {{ reset.expires_minutes }} minutes. If you did not ask for it, ignore this e-mail.</p>',
        ],
        'password_reset_code' => [
            'subject' => 'Your {{ store.name }} reset code: {{ code.value }}',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>Use this code to reset your {{ store.name }} password:</p><h2>{{ code.value }}</h2><p>The code expires in {{ code.expires_minutes }} minutes. If you did not ask for it, ignore this e-mail.</p>',
        ],
        'email_code' => [
            'subject' => 'Your {{ store.name }} sign-in code: {{ code.value }}',
            'body' => '<p>Hello,</p><p>Use this code to sign in to {{ store.name }}:</p><h2>{{ code.value }}</h2><p>The code expires in {{ code.expires_minutes }} minutes. If you did not ask for it, ignore this e-mail.</p>',
        ],
        'ticket_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ customer.first_name }},</p><p>{{ alert.body }}</p><blockquote>{{ reply.excerpt }}</blockquote><p><a href="{{ ticket.url }}">View ticket {{ ticket.reference }}</a></p>',
        ],
        'welcome' => [
            'subject' => 'Welcome to {{ store.name }}',
            'body' => '<h2>Welcome, {{ customer.first_name }}!</h2><p>Your {{ store.name }} account is ready. Track your orders, save your addresses and check out faster next time.</p><p><a href="{{ store.url }}">Start shopping</a></p>',
        ],
    ],
    'affiliate' => [
        'welcome' => [
            'subject' => 'Welcome to the {{ platform.name }} affiliate program',
            'body' => '<p>Hi {{ affiliate.name }},</p><p>Thanks for joining the {{ platform.name }} affiliate program. We will review your account shortly.</p><p><a href="{{ login.url }}">Sign in</a></p>',
        ],
        'payout_sent' => [
            'subject' => 'Payout {{ payout.reference }} sent',
            'body' => '<p>Hi {{ affiliate.name }},</p><p>We sent your payout of <strong>{{ payout.amount }}</strong> (reference {{ payout.reference }}).</p>',
        ],
        'activated' => [
            'subject' => 'Your {{ platform.name }} affiliate account is active',
            'body' => '<h2>Welcome to the Partner Program, {{ affiliate.name }}</h2><p>Your partner account is approved. Share your link and start earning commission.</p><table><tbody><tr><td>Your link</td><td>{{ referral.url }}</td></tr><tr><td>Promo code</td><td>{{ affiliate.code }}</td></tr></tbody></table><p><a href="{{ login.url }}">Open partner panel</a></p>',
        ],
        'conversion' => [
            'subject' => 'Conversion {{ conversion.status }}: {{ commission.amount }}',
            'body' => '<p>Hi {{ affiliate.name }},</p><p>Your referral {{ store.name }} ({{ plan.name }}) is now <strong>{{ conversion.status }}</strong>. Commission: {{ commission.amount }}.</p><p>{{ conversion.note }}</p><p><a href="{{ dashboard.url }}">Open your dashboard</a></p>',
        ],
        'payout' => [
            'subject' => 'Payout {{ payout.reference }}: {{ payout.status }}',
            'body' => '<p>Hi {{ affiliate.name }},</p><p>Your payout request of <strong>{{ payout.amount }}</strong> via {{ payout.method }} is {{ payout.status }} (reference {{ payout.reference }}).</p><p>{{ payout.note }}</p><p><a href="{{ dashboard.url }}">Open your dashboard</a></p>',
        ],
        'verify_email' => [
            'subject' => 'Verify your e-mail address',
            'body' => '<p>Hi {{ recipient.name }},</p><p>Please confirm your e-mail address to activate your affiliate account.</p><p><a href="{{ verify.url }}">Verify e-mail</a></p><p>The link expires in {{ verify.expires_minutes }} minutes.</p>',
        ],
        'password_reset' => [
            'subject' => 'Reset your affiliate password',
            'body' => '<p>Hi {{ recipient.name }},</p><p>We received a request to reset your affiliate account password.</p><p><a href="{{ reset.url }}">Reset password</a></p><p>The link expires in {{ reset.expires_minutes }} minutes. If you did not ask for it, ignore this e-mail.</p>',
        ],
        'alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open your dashboard</a></p>',
        ],
    ],
    'admin' => [
        'new_vendor' => [
            'subject' => '{{ alert.title }}',
            'body' => '<h2>New store</h2><p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Review the store</a></p>',
        ],
        'payment_failed' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the payment</a></p>',
        ],
        'password_reset' => [
            'subject' => 'Reset your {{ platform.name }} admin password',
            'body' => '<p>Hi {{ recipient.name }},</p><p>We received a request to reset your admin password.</p><p><a href="{{ reset.url }}">Reset password</a></p><p>The link expires in {{ reset.expires_minutes }} minutes. If you did not ask for it, contact your super admin.</p>',
        ],
        'contact_message' => [
            'subject' => 'Contact form: {{ contact.subject }}',
            'body' => '<p>New message from <strong>{{ contact.name }}</strong> ({{ contact.email }}, {{ contact.phone }}).</p><p><strong>{{ contact.subject }}</strong></p><blockquote>{{ contact.message }}</blockquote><p>Reply to this e-mail to answer the sender.</p>',
        ],
        'alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open in the admin panel</a></p>',
        ],
        'welcome' => [
            'subject' => '{{ alert.title }}',
            'body' => '<h2>Welcome to {{ platform.name }}</h2><p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the admin panel</a></p>',
        ],
        'password_changed' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p>If this was not you, reset your password now and contact your super admin.</p><p><a href="{{ action.url }}">Review your account</a></p>',
        ],
        'team_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the team</a></p>',
        ],
        'order_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the order</a></p>',
        ],
        'return_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open returns</a></p>',
        ],
        'request_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the request</a></p>',
        ],
        'billing_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open billing</a></p>',
        ],
        'vendor_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">Open the store</a></p>',
        ],
    ],
    'support' => [
        'ticket_reply' => [
            'subject' => '[{{ ticket.reference }}] New reply: {{ ticket.subject }}',
            'body' => '<p>Hi {{ recipient.name }},</p><p>{{ reply.author }} replied to your ticket <strong>{{ ticket.reference }}</strong> “{{ ticket.subject }}”:</p><blockquote>{{ reply.excerpt }}</blockquote><p><a href="{{ ticket.url }}">View the conversation</a></p>',
        ],
    ],
    'central' => [
        'newsletter_confirmation' => [
            'subject' => 'Confirm your {{ platform.name }} newsletter subscription',
            'body' => '<p>Hello,</p><p>Please confirm that you want to receive the {{ platform.name }} newsletter.</p><p><a href="{{ confirm.url }}">Confirm subscription</a></p><p>If you did not sign up, ignore this e-mail. You can <a href="{{ unsubscribe.url }}">unsubscribe</a> at any time.</p>',
        ],
    ],
];
