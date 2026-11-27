# Notification matrix — event audit (decision #118)

Source of truth for the notification + e-mail system. Audited from code on 2026-11-27: 79 event classes (`backend/app/Modules/*/Events`), 42 notification classes + the `PanelNotification` base, all listener registrations in `*/ModuleServiceProvider.php` (`Event::listen` / `Event::subscribe`; no `#[ListensTo]` or EventServiceProvider in use).

**Conventions**

- *Audience*: `admin(<perm>)` = active admins with that permission (`AdminRepository::activeWithPermission`, central DB). `store owners` = `VendorStaffRepository::activeOwners()` in the store DB. `store staff(<perm>)` = active staff with that tenant permission. `owner portal` = central `Owner` (HasPanelInbox, surface `owner`). `customer` = store `Customer`. `affiliate` = central `Affiliate`.
- *Channels*: `db` = database inbox, `mail`, `rvb` = Reverb broadcast, `push` = FCM via `QueueCustomerPush` (runs on every `database` NotificationSent for a `Customer`, #108).
- Central users (admin, owner, affiliate) get channels from `NotificationPreferenceService::channelsFor(category)`; `VendorStaff` get db+mail+rvb filtered per channel by `EnforceVendorNotificationPreferences` (#94, topic from `VendorNotificationTopic::fromType("<category>.<event>")`, low-stock e-mail off by default). Customers: `CustomerNotificationPreferences` (order_updates / shipping_updates / returns / support / price_drops / back_in_stock / promotions).
- *Template key* `—` = mail is a plain `MailMessage` (no editable template today). `*.alert` = the new generic template batch 2 routes `PanelNotification::toMail()` through (`alert.title`, `alert.body`, `action.url`).
- *Wired?* yes = listener + notification exist for every intended audience/channel; partial = some audiences/channels or mail not templated; no = nothing sent; n/a = intentionally no notification.

## Event matrix

### Affiliates

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `AffiliateActivated` | Admin activates an affiliate account | affiliate | db, mail, rvb | affiliate.activated | yes (`SendAffiliateNotifications::activated` → `AffiliateNotification`) | none |
| `AffiliateConversionApproved` | Commission approved (store paid) | affiliate | db, mail, rvb | affiliate.conversion | yes | none |
| `AffiliateConversionReversed` | Commission reversed (refund) | affiliate | db, mail, rvb | affiliate.conversion | yes | also notify admin(affiliates.conversions.view) db+rvb, mail via admin.alert |
| `AffiliatePayoutPaid` | Payout marked paid | affiliate | db, mail, rvb | affiliate.payout | yes | none (affiliate.payout_sent is a legacy duplicate key — keep unused or alias) |
| `AffiliatePayoutRequestRejected` | Payout request rejected | affiliate | db, mail, rvb | affiliate.payout | yes | none |

### AfterSales

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `ReturnStatusChanged` | Return opened / status moves / staff message | customer (return_*); store owners (store_return_opened/updated); admin(returns.view) on open + approved/rejected/refunded/cancelled/closed | customer db+mail+push; owners db+mail+rvb; admin db+mail+rvb | customer: — ; owners: — (vendor.alert) ; admin: — (admin.alert) | partial — customer mail untemplated, store side owners only (not staff with returns.view); customer e-mail switch uses `order_updates`, not `returns` | `order.return_update` (customer), `vendor.return_requested` (store, topic return_requested), admin.alert; send store side to staff(returns.view); use `CustomerNotificationType::Returns` for mail |
| `ReturnCustomerMessaged` | Customer writes on a return | store owners; admin(returns.view) | db, mail, rvb | vendor.alert / admin.alert | partial (mail untemplated) | route mail through vendor.alert / admin.alert; staff(returns.view) |
| `RefundCompleted` | Refund succeeded | customer; store owners; admin(refunds.view) | customer db+mail+push; others db+mail+rvb | order.refund_processed (customer); vendor.alert / admin.alert | partial (store/admin mail untemplated). Also drives ledger + `RequestAffiliateReversal` | vendor.alert / admin.alert |
| `RefundFailed` | Gateway refund failed | store owners; admin(refunds.view) | db, mail, rvb | vendor.alert / admin.alert | partial (mail untemplated) | `admin.refund_failed` specific template (action needed) |

### Ai

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `AdminTranslationRunUpdated` | Live progress broadcast (ShouldBroadcastNow) | none — UI progress only | rvb (event itself) | — | n/a | none. Completion/failure is notified by `AdminTranslationRunService` → `AdminTranslationRunNotification` to admin(kind manage permission), db+mail+rvb, mail → admin.alert |
| `StoreTranslationRunUpdated` | Live progress broadcast | none — UI progress only | rvb | — | n/a | none. `StoreTranslationRunService` → `StoreTranslationRunNotification` to store owners (vendor.alert) |
| `VideoAdUpdated` | Live video-ad status broadcast | none — UI progress only | rvb | — | n/a | none. `VideoAdGenerator` → `VideoAdNotification` to creator or owners (vendor.alert) |

### Billing

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `AddonPurchasePaid` | Add-on (AI/feature) purchase paid | store owners; admin(finance.purchases.view) | db, mail, rvb | vendor.invoice_paid (covered via InvoicePaid) | partial — no own notification; receipt only through the invoice's `InvoicePaid` | in-app db+rvb to owners "add-on active" (vendor.alert); admin db (admin.alert) |
| `AffiliateConversionReversalRequested` | Refund asks Affiliates to reverse a commission | none (internal hand-off) | — | — | n/a (`ReverseConversionOnRefund` → fires `AffiliateConversionReversed`, which notifies) | none |
| `InvoiceIssued` | Platform invoice issued to a store | admin(finance.invoices.view); store owner e-mail (bill-to / owner_email) | admin db+mail+rvb; owner mail | admin: — (admin.alert); owner: vendor.invoice_issued | partial (admin mail untemplated; owner gets mail only, no owner-portal inbox) | admin.alert; also `Owner` inbox db+rvb |
| `InvoicePaid` | Invoice settled | admin(finance.invoices.view); owner mail | as above | vendor.invoice_paid | partial (same) | same as InvoiceIssued |
| `InvoiceVoided` | Invoice voided | admin(finance.invoices.view); owner | db, mail, rvb | — | no | `vendor.invoice_voided` + admin.alert |
| `LanguagePurchasePaid` | Store bought a language → translation run | none directly (`QueueStoreTranslationRun`); receipt via InvoicePaid, completion via StoreTranslationRunNotification | — | — | n/a | none |
| `PaymentCaptured` | Gateway capture | none directly — internal fan-out (renew subscription, settle registration, settlement outcome, affiliate approval) | — | — | n/a (downstream events notify) | none |
| `PaymentFailed` | Gateway payment failed | admin(finance.payment-logs.view); owner mail | admin db+mail+rvb; owner mail | vendor.payment_failed (owner); admin: — | partial — admin.payment_failed key exists but is not used (mail goes through PanelNotification plain mail) | route admin mail to admin.payment_failed |
| `PaymentRefunded` | Platform payment refunded | admin(finance.payment-logs.view); owner mail | as above | vendor.payment_refunded | partial (admin mail untemplated) | admin.alert |
| `SubscriptionActivated` | Plan activated | admin(subscriptions.view); owner mail | admin db+mail+rvb; owner mail | vendor.subscription_activated | partial (admin untemplated) | admin.alert (MAIL-008) |
| `SubscriptionCancelled` | Plan cancelled | admin(subscriptions.view); owner | db, mail, rvb | — | no | `vendor.subscription_cancelled`, `admin.subscription_cancelled` (MAIL-010) |
| `SubscriptionExpired` | Plan expired | admin(subscriptions.view); owner mail | as above | vendor.subscription_expired | partial | admin.alert |
| `SubscriptionPastDue` | Renewal failed | admin(subscriptions.view); owner mail | as above | vendor.subscription_past_due | partial | admin.alert |
| `SubscriptionRenewed` | Plan renewed | owner; admin(subscriptions.view) db only | db, mail, rvb | — | no | `vendor.subscription_renewed` |

### Catalog

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `CatalogImportFinished` | Admin catalog import finished | the admin who started it | db, mail, rvb | — (admin.alert) | partial (mail untemplated) | admin.alert |
| `ProductCreated` | Central product created | none — internal (image index, fixed shipping) | — | — | n/a | none |
| `ProductOfferedToStores` | Admin offers a product to stores | owner portal (or owner_email); store staff(catalog.products.view) | owner db+mail+rvb; staff db+rvb | — (vendor.alert) | partial (mail untemplated; staff no mail) | `vendor.product_offered` |
| `ProductUpdated` | Central product updated | none — internal | — | — | n/a | none |
| `Requests/EditRequestReviewed` | Admin approves/rejects an edit request | owner portal; staff(catalog.products.view) | owner db+mail+rvb; staff db+rvb | — | partial | `vendor.request_reply` (shared catalog-request template) |
| `Requests/RequestCancelledByVendor` | Vendor cancels a product/manufacturing/brand request | admin(requests.<kind>.manage) | db, mail, rvb | — (admin.alert) | partial | admin.alert |
| `Requests/RequestMessagePosted` | Message on a request thread | vendor msg → admin(requests.<kind>.manage); admin msg → owner portal + staff(requests.<kind>.view) | admin/owner db+mail+rvb; staff db+rvb | — | partial (untemplated; staff never mailed) | `vendor.request_reply` (topic request_replies), admin.alert; add mail to staff via `StoreRequestNotification` subject to #94 |
| `Requests/RequestPaymentRequested` | Admin requests / cancels a payment on a request | owner portal; staff(requests.<kind>.view) | as above | — | partial | `vendor.request_payment` |
| `Requests/RequestStatusChanged` | Request status / archive / restore | owner portal; staff(requests.<kind>.view) | as above | — | partial | `vendor.request_reply` |
| `Requests/RequestSubmitted` | Vendor submits a request | admin(requests.<kind>.manage) | db, mail, rvb | — (admin.alert) | partial | admin.alert |
| `Requests/RequestThreadUpdated` | Live thread refresh (ShouldBroadcast) | none — UI refresh | rvb | — | n/a | none |
| `Requests/TenantChangeReviewed` | Admin reviews a store change request | owner portal; staff(requests.tenant-changes.view) | owner db+mail+rvb; staff db+rvb | — | partial | `vendor.request_reply` |
| `Store/OwnProductSaved` | Store saves its own product | none — internal (image index) | — | — | n/a | none |
| `Store/StoreProductsChanged` | Store selection/prices changed | none — cache/AI translation | — | — | n/a | none |

### Commerce

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `CartItemAdded` | Item added to cart | none — reporting fact | — | — | n/a | none |
| `OrderCancelled` | Order cancelled | customer; store owners; admin(orders.view) | customer db+mail+push; owners db+rvb+mail; admin db+mail+rvb | order.cancelled; vendor.alert / admin.alert | partial (store/admin mail untemplated; staff with orders.view not notified) | `vendor.order_cancelled`; staff(orders.view) |
| `OrderDelivered` | Order delivered | customer | db, mail (shipping_updates), push | order.delivered | yes | none |
| `OrderFulfilmentUpdated` | Fulfilment step (out for delivery / delayed notify customer) | customer (only OutForDelivery, Delayed, when `notifyCustomer`) | db, mail, push | — | partial (mail untemplated) | `order.shipping_update` (MAIL-014 variants) |
| `OrderPaid` | Order paid → confirmation | customer; store owners; admin(orders.view) | customer db+mail+push; owners db+rvb+mail; admin db+mail+rvb | order.confirmation (also MAIL-013/017, D1); vendor.alert; admin.alert | partial (store/admin mail untemplated) | `vendor.new_order` (topic new_order), `admin.order_alert` (MAIL-021); staff(orders.view) |
| `OrderPlaced` | Order created (pending payment) | none — confirmation is sent on OrderPaid (also fires for COD once moved to paid) | — | — | n/a (no listener) | none |
| `OrderShipped` | Order shipped | customer | db, mail (shipping_updates), push | order.shipped | yes | none |
| `OrderStatusChanged` | Any status transition | none — ledger + reporting; customer-facing transitions have their own events | — | — | n/a | none |
| `PromotionsChanged` | Coupons/flash sales changed | none — cache bust | — | — | n/a | none |
| `SettlementPaid` | Store paid its settlement | admin(finance.settlements.view); store owners | db, mail, rvb | — | partial — ledger only; owners get `settlement_confirmed` only on manual settlements (`ManualSettlementService` → FinanceAlertNotification) | `admin.settlement_received` (MAIL-028), `vendor.settlement_receipt` (MAIL-027) |

### Content

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `ContactMessageReceived` | Central contact form | admin(content.contact.view); support inbox (General settings) | admin db+mail+rvb; inbox mail | admin.contact_message | partial (ContactMessageMail plain) | route ContactMessageMail through admin.contact_message |

### Engagement

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `ReviewModerated` | Store approves/rejects a review | customer (author) | db, push (mail optional) | — | no | in-app + push to customer "review published"; no mail by default |
| `ReviewSubmitted` | New product review | store owners | db, mail, rvb | — (vendor.alert) | partial | `vendor.review_submitted`; staff with reviews permission |
| `SupportRequestReceived` | Storefront contact / support form | store owners | db, mail, rvb | — (vendor.alert) | partial | `vendor.contact_message` |

### Identity

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `AdminAccountCreated` | New admin account | new admin (welcome); other super-admins | db, mail, rvb | — (admin.alert) | partial | `admin.welcome` |
| `AdminPasswordChanged` | Admin password changed | the admin | db, mail, rvb | — (admin.alert) | partial | `admin.password_changed` (security mail, should not be switchable off) |
| `AdminRoleChanged` | Admin role changed | the admin | db, mail, rvb | — (admin.alert) | partial | admin.alert |
| `CustomerRegistered` | Storefront/app sign-up | store owners (db only); customer (welcome) | owners db; customer mail | — | partial — owners in-app only; no customer welcome (MAIL-042) | `customer.welcome` (MAIL-042); owners add rvb |
| `VendorStaffJoined` | Invited staff activated | store owners (except the member) | db, mail, rvb | — (vendor.alert) | partial | vendor.alert |

### Inventory

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `StockChanged` | Product/branch stock total changed | store staff(catalog.products.view) when ≤ low-stock threshold / 0; customers subscribed to back-in-stock | staff db+rvb (+mail off by default, #94 topic low_stock); customer db+push+mail (back_in_stock) | — | no (only checkout blocks use it) | `vendor.low_stock` (events `low_stock` / `out_of_stock`); `customer.back_in_stock` |

### Localization

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `StaticTextsNeedTranslation` | New static texts found | none — triggers translation tooling | — | — | n/a | none |
| `StoreTranslationRunQueued` | Store translation queued | none — starts the job (`StartStoreTranslation`) | — | — | n/a | none |

### Notifications

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `EmailTemplatesSynced` | Central templates synced to stores | none — technical sync (`SyncStoreEmailTemplates`) | — | — | n/a | none |

### Platform

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `ComplianceReviewed` | KYC approved / rejected / needs info | store owner (owner_email) | mail (+ owner portal db+rvb) | vendor.compliance_decision | partial (plain mail, no inbox) | template vendor.compliance_decision; owner portal inbox (MAIL-033) |
| `ComplianceSubmitted` | KYC submitted / resubmitted | admin(vendors.kyc.manage) | db, mail, rvb | — (admin.alert) | partial | admin.alert |
| `DomainConnected` | Custom domain verified | store owners; admin(vendors.domains.view) db | db, mail, rvb | — | no | `vendor.domain_status` (MAIL-032; DNS-failed needs a new event) |
| `RegistrationCompleted` | Vendor registration finished | none directly (affiliate referral); welcome sent on TenantProvisioned | — | — | n/a | none |
| `TenantProvisioned` | Store DB ready | owner_email (welcome); admin(vendors.view) | owner mail; admin db+mail+rvb | vendor.welcome; admin.new_vendor | partial — both keys exist but VendorWelcomeNotification is plain MailMessage and VendorProvisionedNotification uses PanelNotification mail (MAIL-003 todo) | render vendor.welcome / admin.new_vendor |
| `TenantProvisioningFailed` | Provisioning failed | admin(vendors.manage) | db, mail, rvb | — (admin.alert) | partial | admin.alert |

### Storefront

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `BannersChanged` | Banners edited | none — cache bust | — | — | n/a | none |
| `ProductViewed` | PDP view | none — reporting fact | — | — | n/a | none |

(Custom themes have no event: `CustomThemeService` sends `CustomThemeSubmittedNotification` to admin(storefront.theme-reviews.manage) and `CustomThemeReviewedNotification` / `CustomThemeRenderFailedNotification` to store owners directly — db+mail+rvb, mail via admin.alert / vendor.alert; proposed `vendor.theme_reviewed` for MAIL-034.)

### Support

| Event | What happens | Audience | Channels | Email template key | Wired today? | Batch-2 action |
|---|---|---|---|---|---|---|
| `CustomerTicketOpened` | Customer opens a store ticket | store staff(support.view) | db, rvb | — | yes (db+rvb by design, #108) | optional mail via vendor.alert (topic request_replies) |
| `CustomerTicketReplied` | Customer ↔ store reply | customer msg → staff(support.view); store msg → customer | staff db+rvb; customer db+mail(support)+push | customer.ticket_update (customer) | partial (customer mail plain) | render customer.ticket_update |
| `CustomerTicketStatusChanged` | Resolve / close / reopen | customer close → staff(support.view); store change → customer | as above | customer.ticket_update | partial | render customer.ticket_update |
| `TicketAssigned` | Platform ticket assigned | assigned admin (support.tickets.view) | db, rvb (no mail) | — | yes | none |
| `TicketOpened` | Vendor/owner opens platform ticket | assignee or admin(support.tickets.manage) | db, mail, rvb | support.ticket_reply | yes | none |
| `TicketReplied` | Platform ticket reply | requester reply → admins; admin reply → staff(support.view) (+mail to opener) and owner portal | db, mail, rvb | support.ticket_reply | yes | none |
| `TicketStatusChanged` | Platform ticket status | requester → admins; admin → staff(support.view) + owner portal | db, rvb (no mail) | — | yes | none |
| `TicketThreadUpdated` | Live thread refresh (ShouldBroadcast) | none — UI refresh | rvb | — | n/a | none |

## Notification classes → template keys

| Class | Base | Channels (today) | Template key(s) today | Key(s) after batch 2 |
|---|---|---|---|---|
| Affiliates/`AffiliateNotification` | PanelNotification | central prefs (db, mail, rvb) | affiliate.activated, affiliate.conversion, affiliate.payout | same |
| Affiliates/`AffiliateVerifyEmailNotification` | Notification | mail | — | affiliate.verify_email |
| Ai/`AdminTranslationRunNotification` | PanelNotification | central prefs | — | admin.alert |
| Ai/`StoreTranslationRunNotification` | PanelNotification | staff db+mail+rvb (#94) | — | vendor.alert |
| Ai/`VideoAdNotification` | PanelNotification | staff | — | vendor.alert |
| Api/`CustomerPasswordResetCodeNotification` | Notification | mail | — | customer.password_reset_code |
| Billing/`BillingAlertNotification` | PanelNotification | central prefs | — | admin.alert (admin.payment_failed for `payment_failed`) |
| Billing/`FinanceAlertNotification` | PanelNotification (own via) | db, rvb, mail | — | vendor.alert → `vendor.payout_sent` / `vendor.settlement_receipt` |
| Billing/`VendorBillingMail` | Notification | mail | vendor.invoice_issued, vendor.invoice_paid, vendor.payment_failed, vendor.payment_refunded, vendor.subscription_activated, vendor.subscription_past_due, vendor.subscription_expired | same (+ new subscription/invoice keys) |
| Catalog/`CatalogImportFinishedNotification` | PanelNotification | central prefs | — | admin.alert |
| Catalog/`RequestUpdateNotification` | PanelNotification (anonymous → mail) | admins / owner prefs; mail to owner_email | — | admin.alert (admins), `vendor.request_reply` / `vendor.request_payment` (owner) |
| Catalog/`StoreRequestNotification` | RequestUpdateNotification | db, rvb | — (no mail) | vendor.request_reply if mail added |
| Commerce/`OrderAlertNotification` | PanelNotification (`forStore` via) | admin prefs; store owners db+rvb+mail | — | `admin.order_alert`, `vendor.new_order`, `vendor.order_cancelled`, `vendor.return_requested`, admin.alert/vendor.alert for the rest |
| Commerce/`OrderCustomerNotification` | Notification | db, mail (customer prefs), push | order.confirmation, order.shipped, order.delivered, order.cancelled, order.refund_processed; `null` for return_* / shipping_* | + `order.return_update`, `order.shipping_update` |
| Content/`ContactMessageMail` | Notification | mail | — | admin.contact_message |
| Content/`ContactMessageNotification` | PanelNotification | central prefs | — | admin.alert |
| Content/`NewsletterConfirmationNotification` | Notification | mail | — | central.newsletter_confirmation |
| Engagement/`StoreEngagementNotification` | PanelNotification | staff | — | vendor.alert → `vendor.review_submitted`, `vendor.contact_message` |
| Identity/`AdminPasswordChangedNotification` | PanelNotification | central prefs | — | admin.alert → `admin.password_changed` |
| Identity/`AdminRoleChangedNotification` | PanelNotification | central prefs | — | admin.alert |
| Identity/`AdminTeamMemberAddedNotification` | PanelNotification | central prefs | — | admin.alert |
| Identity/`AdminWelcomeNotification` | PanelNotification | central prefs | — | admin.alert → `admin.welcome` |
| Identity/`CustomerEmailCodeNotification` | Notification | mail | — | customer.email_code |
| Identity/`NewCustomerRegisteredNotification` | Notification | db | — (no mail) | none |
| Identity/`ResetPasswordNotification` | Notification | mail | — | admin.password_reset / affiliate.password_reset / vendor.password_reset / customer.password_reset (by notifiable: Admin, Affiliate, VendorStaff, Customer) |
| Identity/`StoreLoginPasswordResetNotification` | Notification | mail | — | vendor.password_reset (central store-login / owner) |
| Identity/`VendorStaffInvitationNotification` | Notification | mail | — | vendor.staff_invitation |
| Identity/`VendorTeamMemberJoinedNotification` | PanelNotification | staff | — | vendor.alert |
| Identity/`VendorVerifyEmailNotification` | Notification | mail | — | vendor.verify_email |
| Platform/`ComplianceActionNotification` | Notification | mail | — | vendor.compliance_decision |
| Platform/`ComplianceSubmittedNotification` | PanelNotification | central prefs | — | admin.alert |
| Platform/`ProvisioningFailedNotification` | PanelNotification | central prefs | — | admin.alert |
| Platform/`RegistrationLinkNotification` | Notification | mail | — (key vendor.registration_link exists, unused) | vendor.registration_link |
| Platform/`RegistrationPaymentReceiptNotification` | Notification | mail | — | vendor.registration_receipt |
| Platform/`VendorProvisionedNotification` | PanelNotification | central prefs | — (key admin.new_vendor exists, unused) | admin.new_vendor |
| Platform/`VendorWelcomeNotification` | Notification | mail | — (key vendor.welcome exists, unused) | vendor.welcome |
| Storefront/`CustomThemeRenderFailedNotification` | PanelNotification | staff | — | vendor.alert |
| Storefront/`CustomThemeReviewedNotification` | PanelNotification | staff | — | vendor.alert → `vendor.theme_reviewed` |
| Storefront/`CustomThemeSubmittedNotification` | PanelNotification | central prefs | — | admin.alert |
| Support/`CustomerTicketNotification` | Notification | db, mail (support pref), push | — | customer.ticket_update |
| Support/`StoreSupportNotification` | SupportTicketNotification | db, rvb (+mail to opener) | support.ticket_reply (inherited toMail) | same |
| Support/`SupportTicketNotification` | PanelNotification | central prefs (mail only when `withMail`) | support.ticket_reply | same |
| Notifications/`PanelNotification` (base) | — | `channelsFor(category)` / VendorStaff all three | — (plain title/body mail) | admin.alert / vendor.alert / affiliate.alert by notifiable unless the subclass has a specific key |

Keys in the enum not sent by any class: `affiliate.welcome` (no affiliate registration welcome is sent), `affiliate.payout_sent` (superseded by `affiliate.payout`), `admin.new_vendor`, `admin.payment_failed`, `vendor.welcome`, `vendor.registration_link` (until batch 2 renders them).

## Decision #118 defaults

- **D1** — MAIL-013 (order processing) and MAIL-017 (payment receipt) are merged into `order.confirmation`, sent on `OrderPaid` (payment summary block in the same template). No separate key.
- **D2** — wallet mails MAIL-025 / MAIL-026 are dropped: the ledger replaced the wallet (`PostLedgerEntries`); store money movements are covered by payout / settlement mails.
- **D3** — PDF invoices/receipts use the existing print views (`storefront.orders.invoice`, admin/vendor invoice print); a headless browser only if a real attachment is needed later. No new PDF package now (MAIL-020, MAIL-047).
- Client can override any of the three.

## MAIL-* coverage

| Row | Subject | Key |
|---|---|---|
| MAIL-001 | Layout (central / tenant, RTL) | shared layout (not a key) |
| MAIL-002 | Registration link | vendor.registration_link |
| MAIL-003 | Tenant welcome | vendor.welcome |
| MAIL-004 | Password reset (all surfaces) | admin.password_reset, vendor.password_reset, affiliate.password_reset, customer.password_reset, customer.password_reset_code (app) |
| MAIL-005 | Customer e-mail verification | customer.email_code |
| MAIL-006 | Subscription activated (tenant) | vendor.subscription_activated |
| MAIL-007 | Subscription renewal reminder (tenant) | proposed `vendor.subscription_renewal_reminder` |
| MAIL-008 | Admin: subscription activated | admin.alert (BillingAlert `subscription_activated`) |
| MAIL-009 | Admin: renewal reminder | proposed `admin.subscription_renewal_reminder` |
| MAIL-010 | Admin: subscription cancelled | proposed `admin.subscription_cancelled` |
| MAIL-011 | Admin: invoice issued | admin.alert (BillingAlert `invoice_issued`) |
| MAIL-012 | Order confirmation | order.confirmation |
| MAIL-013 | Order processing | dropped (D1) → order.confirmation |
| MAIL-014 | Shipping update | order.shipped + proposed `order.shipping_update` (out for delivery / delayed) |
| MAIL-015 | Order delivered | order.delivered |
| MAIL-016 | Order cancelled | order.cancelled |
| MAIL-017 | Payment receipt | dropped (D1) → order.confirmation |
| MAIL-018 | Refund processed | order.refund_processed |
| MAIL-019 | Return update | proposed `order.return_update` |
| MAIL-020 | Order invoice (PDF) | order.confirmation link to print view (D3); no attachment |
| MAIL-021 | Admin: new order | proposed `admin.order_alert` |
| MAIL-022 | Admin: shipping escalation | proposed `admin.shipping_escalation` (needs new event) |
| MAIL-023 | Admin: gateway limit warning | proposed `admin.gateway_limit_warning` (needs new event) |
| MAIL-024 | Admin: gateway limit block | proposed `admin.gateway_limit_block` (needs new event) |
| MAIL-025 | Wallet receipt (tenant) | dropped (D2) |
| MAIL-026 | Admin: vendor wallet transaction | dropped (D2) |
| MAIL-027 | Settlement receipt (vendor) | proposed `vendor.settlement_receipt` |
| MAIL-028 | Settlement received (admin) | proposed `admin.settlement_received` |
| MAIL-029 | Payout sent (tenant) | proposed `vendor.payout_sent` |
| MAIL-030 | Request update | proposed `vendor.request_reply` / `vendor.request_payment` |
| MAIL-031 | Edit request reviewed | proposed `vendor.request_reply` |
| MAIL-032 | Domain connected / DNS failed | proposed `vendor.domain_status` |
| MAIL-033 | Compliance reviewed | vendor.compliance_decision |
| MAIL-034 | Custom theme reviewed | proposed `vendor.theme_reviewed` |
| MAIL-035 | Add-on receipt | vendor.invoice_paid |
| MAIL-036 | AI job finished / failed | vendor.alert / admin.alert |
| MAIL-037 | Support ticket reply | support.ticket_reply |
| MAIL-038 | Affiliate activated | affiliate.activated |
| MAIL-039 | Affiliate conversion | affiliate.conversion |
| MAIL-040 | Affiliate payout | affiliate.payout |
| MAIL-041 | Contact form → admin | admin.contact_message |
| MAIL-042 | Customer welcome | proposed `customer.welcome` |
| MAIL-043 | Printable customer invoice | print view (not a mail key, D3) |
| MAIL-044 | Printable order receipt | print view (D3) |
| MAIL-045 | Printable shipping label | print view (D3) |
| MAIL-046 | Printable platform invoice | print view (D3) |
| MAIL-047 | Printable payout / settlement receipt | print view (D3) |

## Batch 2 wiring list

> **Batch 3 delivered (#124 h, notif-email3) — coded, untested:** items **5** (`order.shipping_update` / `order.return_update` replace `order.update` for shipping steps and returns), **7** (stock alerts: `StockChanged` now carries the previous total; own-product changes fire `StoreStockChanged`; team alert `vendor.low_stock` for low / out of stock — threshold = store-product min_stock → central min_stock → store setting `low_stock_threshold` (card on VEN-087, default 5), topic low_stock; back-in-stock: tenant `stock_alert_subscriptions`, `POST/GET /store-api/v1/products/{id}/stock-alerts`, "Notify me" form on the out-of-stock product page of Ecommet / Elora / Souqify, `customer.back_in_stock` sent once (inbox + push + mail per back_in_stock pref; guests mail only)), **9** (SettlementPaid → owners `vendor.settlement_receipt`, admins `settlement_received`; manual `settlement_confirmed` now uses `vendor.settlement_receipt`), **10** (`billing:settlement-reminders` weekly → `vendor.settlement_due`, once per store and ISO week), **15** (`billing:renewal-reminders` daily → owner `vendor.subscription_renewal_reminder` 7 / 3 / 1 days + owner inbox; admins BillingAlert `renewal_reminder` via `admin.billing_alert`), **17 / 20** (owner-portal inbox copies of every billing mail and the compliance decision, `OwnerInboxNotification`, db + Reverb, no mail), **21** (DNS failed: `DomainDnsFailed` → owner `vendor.domain_dns_failed` + owner inbox, admins vendors.domains.view inbox), **24** (ReviewModerated → author inbox + push; published also mails `customer.review_published`), **29** (`AffiliatePayoutRequested` → admins affiliates.payouts.manage, `admin.affiliate_payout_request`), **30** (MAIL-022 = delayed fulfilment → `admin.shipping_escalation`; MAIL-023 / 024 = ADM-100c gateway limits → `admin.gateway_limit_warning` / `admin.gateway_limit_block` + store owners `vendor.gateway_limit`). Every templated mail now has a plain-text part (`MailPlainText`, also TemplatedMail / SMTP test mails). Registry: 77 keys. Still open: MAIL-009 has no own key (uses `admin.billing_alert`); `admin.new_vendor` / `affiliate.welcome` / `affiliate.payout_sent` remain unused keys; AffiliateConversionReversed admin alert (rest of item 29).
>
> **Batch 2 delivered (#124 g):** items **2, 3, 6, 8, 11, 12 (template), 13, 14, 16, 22, 23, 26, 28** (specific templates per event + new listeners for SubscriptionCancelled / SubscriptionRenewed / InvoiceVoided / CustomerRegistered welcome / DomainConnected), **4** switch fix and staff recipients. Still open: 5 (specific shipping key — uses order.update), 7, 9, 10, 15, 17, 20 owner inbox, 21 DNS-failed, 24, 29 payout-request event, 30.
>
> **Batch 1 delivered (#124, notif-email1):** items **1** (all `PanelNotification` mail → `admin.alert` / `vendor.alert` / `affiliate.alert`), **19**, **25** (customer side), **27** and **31** are done; **18** done for `VendorWelcomeNotification` → `vendor.welcome` (`admin.new_vendor` still unused — `VendorProvisionedNotification` mails `admin.alert`); **20** template done (`vendor.compliance_decision`), owner-portal inbox still open; **4 / 5** mail now goes through the generic `order.update` key (`alert.title` / `alert.body` + order variables) — the specific `order.return_update` / `order.shipping_update` keys and the `Returns` switch fix remain. Every mail-sending class is templated today (column "Template key(s) today" above predates batch 1; the architecture test `EmailTemplateRegistryTest` now enforces it). Registry keys no class sends yet: `admin.new_vendor`, `admin.payment_failed`, `affiliate.welcome`, `affiliate.payout_sent`.

1. **PanelNotification mail → generic templates.** `PanelNotification::toMail()` renders `admin.alert` / `vendor.alert` / `affiliate.alert` (by notifiable: Admin / VendorStaff+Owner / Affiliate) with `alert.title`, `alert.body`, `action.url`, unless the subclass maps a specific key. Covers ~25 classes in one change.
2. **OrderPaid → store + admin specific templates.** Store owners + staff(orders.view): db, rvb, mail — `vendor.new_order` (topic new_order). Admin(orders.view): db, mail, rvb — `admin.order_alert` (MAIL-021).
3. **OrderCancelled → store template.** Store owners + staff(orders.view): db, rvb, mail — `vendor.order_cancelled`. Admin uses admin.alert.
4. **ReturnStatusChanged / ReturnCustomerMessaged.** Customer: db, mail, push — `order.return_update` (MAIL-019); mail switch must use `CustomerNotificationType::Returns`, not `order_updates`. Store owners + staff(returns.view): db, rvb, mail — `vendor.return_requested` (topic return_requested).
5. **OrderFulfilmentUpdated (out for delivery / delayed).** Customer: db, mail (shipping_updates), push — `order.shipping_update`.
6. **RefundFailed.** Admin(refunds.view): db, mail, rvb — `admin.refund_failed`; store owners vendor.alert.
7. **StockChanged → low stock / out of stock (new listener).** Store staff(catalog.products.view): db, rvb, mail (mail off by default, #94) — `vendor.low_stock`, events `low_stock` / `out_of_stock`. Customer back-in-stock subscribers (needs subscription storage): db, push, mail (back_in_stock pref) — `customer.back_in_stock`.
8. **Payouts (PayoutService → FinanceAlertNotification `payout_*`).** Store owners: db, rvb, mail — `vendor.payout_sent` (topic payout_sent).
9. **SettlementPaid (new listener) + manual `settlement_confirmed`.** Store owners: db, rvb, mail — `vendor.settlement_receipt` (MAIL-027). Admin(finance.settlements.view): db, mail, rvb — `admin.settlement_received` (MAIL-028).
10. **Settlement due reminder (scheduled, no event yet).** Store owners: db, rvb, mail — `vendor.settlement_due` (topic settlement_due exists but nothing sends it).
11. **Catalog request threads** (RequestMessagePosted admin reply, RequestStatusChanged, EditRequestReviewed, TenantChangeReviewed). Owner portal: db, rvb, mail; staff(requests.<kind>.view): db, rvb + mail subject to #94 (`StoreRequestNotification` has no mail today) — `vendor.request_reply`; RequestPaymentRequested — `vendor.request_payment`.
12. **ProductOfferedToStores.** Owner portal + staff(catalog.products.view): db, rvb, mail — `vendor.product_offered`.
13. **SubscriptionCancelled (new listener).** Owner: mail (+ owner portal db, rvb) — `vendor.subscription_cancelled`; admin(subscriptions.view): db, mail, rvb — `admin.subscription_cancelled` (MAIL-010).
14. **SubscriptionRenewed (new listener).** Owner: mail, owner portal db — `vendor.subscription_renewed`.
15. **Subscription renewal reminder (scheduled, no event yet).** Owner: mail + owner portal db — `vendor.subscription_renewal_reminder` (MAIL-007); admin(subscriptions.view): db, mail — `admin.subscription_renewal_reminder` (MAIL-009).
16. **InvoiceVoided (new listener).** Owner: mail — `vendor.invoice_voided`; admin(finance.invoices.view): db, rvb, admin.alert.
17. **Billing owner inbox.** InvoiceIssued/Paid, PaymentFailed/Refunded, Subscription* also go to the `Owner` portal inbox (db, rvb), not only to e-mail. Admin PaymentFailed mail uses existing `admin.payment_failed`.
18. **TenantProvisioned.** Render existing `vendor.welcome` in `VendorWelcomeNotification` (MAIL-003) and `admin.new_vendor` in `VendorProvisionedNotification`.
19. **Registration mails.** `RegistrationLinkNotification` → existing `vendor.registration_link`; `RegistrationPaymentReceiptNotification` → `vendor.registration_receipt`.
20. **ComplianceReviewed.** `ComplianceActionNotification` → `vendor.compliance_decision` (MAIL-033); add owner portal db, rvb.
21. **DomainConnected (new listener) + DNS-failed event (new).** Store owners: db, rvb, mail — `vendor.domain_status` (MAIL-032); admin(vendors.domains.view): db.
22. **CustomerRegistered → customer welcome.** Customer: mail (+ db, push) — `customer.welcome` (MAIL-042). Owners keep db, add rvb.
23. **ReviewSubmitted / SupportRequestReceived.** Store owners + staff with reviews/support permission: db, rvb, mail — `vendor.review_submitted`, `vendor.contact_message`.
24. **ReviewModerated (new listener).** Customer (author): db, push — mail optional via `customer.review_published`.
25. **Customer tickets.** `CustomerTicketNotification` mail → `customer.ticket_update`; staff side (`StoreSupportNotification` customer_*) optional mail via vendor.alert, topic request_replies.
26. **Custom themes (service-sent).** `CustomThemeReviewedNotification` → `vendor.theme_reviewed` (MAIL-034); render-failed vendor.alert; submitted admin.alert.
27. **Auth / account mails to templates.** `ResetPasswordNotification` → admin./vendor./affiliate./customer.password_reset by notifiable; `StoreLoginPasswordResetNotification` → vendor.password_reset; `CustomerPasswordResetCodeNotification` → customer.password_reset_code; `CustomerEmailCodeNotification` → customer.email_code; `AffiliateVerifyEmailNotification` → affiliate.verify_email; `VendorVerifyEmailNotification` → vendor.verify_email; `VendorStaffInvitationNotification` → vendor.staff_invitation; `NewsletterConfirmationNotification` → central.newsletter_confirmation; `ContactMessageMail` → admin.contact_message.
28. **Admin account.** `AdminWelcomeNotification` → `admin.welcome`; `AdminPasswordChangedNotification` → `admin.password_changed` (security, always mailed).
29. **AffiliateConversionReversed → admin.** Admin(affiliates.conversions.view): db, rvb, admin.alert. Affiliate payout *request* has no event — add `AffiliatePayoutRequested` → admin(affiliates.payouts.manage) db, mail, rvb — `admin.affiliate_payout_request`.
30. **Missing events for MAIL-022/023/024.** Add shipping-escalation and gateway-limit events → admin(shipping-queue.view / settings.payment-gateways.view): db, mail, rvb — `admin.shipping_escalation`, `admin.gateway_limit_warning`, `admin.gateway_limit_block`.
31. **Architecture test (#118).** Fail when a notification with `mail` in `via()` has no `EmailTemplateEvent` key (generic alert keys count).
