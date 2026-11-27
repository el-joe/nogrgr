INSERT INTO languages (id, name, code, native_name, direction, is_default, sort_order, is_active, is_free, price) VALUES
 (1, 'English', 'en', 'English', 'ltr', 1, 0, 1, 1, NULL), (11, 'Arabic', 'ar', 'العربية', 'rtl', 0, 1, 1, 1, NULL);
INSERT INTO currencies (id, code, name, sign, conversion_rate) VALUES (1, 'USD', 'US Dollar', '$', 1), (2, 'ZZQ', 'Legacy Test Coin', 'Q', 2.5);
INSERT INTO countries (id, iso2, iso3, currency_code, phone_code, flag_emoji, is_active_for_tenants, is_free, name) VALUES
 (52, 'EG', 'EGY', 'EGP', '+20', '', 1, 1, 'Egypt'), (150, 'SA', 'SAU', 'SAR', '+966', '', 1, 1, 'Saudi Arabia');
INSERT INTO cities (id, country_id, name) VALUES (1, 52, 'Legacy Test Town'), (2, 999, 'Orphan City');
INSERT INTO packages (id, name, status, term, price, products_limit, banners_limit, languages_limit, orders_per_month_limit, ai_calls_limit, image_searches_limit, categories_count, trial_days) VALUES
 (2, 'Pro', 'published', 'yearly', 299.00, -1, 10, 3, 500, 100, 50, 20, 30);
INSERT INTO app_settings (id, `key`, value, type, is_public) VALUES
 (1, 'mail_host', 'smtp.legacy.test', 'string', 0), (2, 'mail_password', '{{MAIL_PASSWORD}}', 'secret', 0), (3, 'header_logo', 'x', 'string', 1);
INSERT INTO payment_gateways (id, name, code, type, status, mode, credentials, transaction_fee_percentage, transaction_fee_fixed) VALUES
 (1, 'Stripe', 'stripe', 'vendor_payments', 'active', 'test', '{{STRIPE_CREDENTIALS}}', 2.9000, 0.30),
 (2, 'Mollie', 'mollie', 'orders', 'inactive', 'test', NULL, 0, 0);
INSERT INTO categories (id, parent_id, status, is_featured, order_number) VALUES (7, NULL, 'published', 1, 1), (8, 7, 'published', 0, 2);
INSERT INTO variations (id, status) VALUES (3, 'active');
INSERT INTO variation_options (id, variation_id, swatch) VALUES (31, 3, '#ff0000');
INSERT INTO products (id, external_id, slug, sku, status, delivery_scope, base_price, sale_price, cost_price, stock, sold_count, min_stock, manage_stock, is_taxable, requires_shipping, weight_grams, fixed_shipping_costs, order_number) VALUES
 (101, NULL, 'hot-sale-65', 'LEG-101', 'published', 'all_zones', 1.76, NULL, 1.76, 40, 3, 0, 1, 0, 1, 250, '{"52": 200, "150": 12.5}', 1);
INSERT INTO product_variants (id, product_id, sku, title, price, stock, position, status) VALUES (1001, 101, 'LEG-101-R', 'Red', 1.76, 10, 0, 'active');
INSERT INTO product_variant_option (product_variant_id, variation_option_id) VALUES (1001, 31);
INSERT INTO product_variation (product_id, variation_id) VALUES (101, 3);
INSERT INTO category_product (category_id, product_id, sort_order) VALUES (8, 101, 0);
INSERT INTO product_labels (id, text, translations, icon, active, sort_order) VALUES (5, 'Hot', '{"en":"Hot","ar":"رائج"}', 'bolt', 1, 0), (6, 'Eco', '{"en":"Eco"}', 'leaf', 1, 1);
INSERT INTO product_label_product (product_label_id, product_id) VALUES (5, 101);
INSERT INTO product_badges (id, text, active) VALUES (9, 'best-selling', 1);
INSERT INTO product_badge_product (product_badge_id, product_id, country_id, sort_order) VALUES (9, 101, NULL, 0);
INSERT INTO files (id, path, storage_type, file_type, model_type, model_id, sort_order) VALUES
 (501, 'products/101/a.png', 'public', 'image', 'App\\Models\\Product', 101, 0),
 (502, 'products/101/a-thumb.png', 'public', 'image', 'App\\Models\\Product', 101, 1),
 (503, 'products/101/missing.png', 'public', 'image', 'App\\Models\\Product', 101, 2);
INSERT INTO translations (language_id, translatable_type, translatable_id, field, value) VALUES
 (1, 'App\\Models\\Category', 7, 'name', 'Home'), (11, 'App\\Models\\Category', 7, 'name', 'المنزل'),
 (1, 'App\\Models\\Category', 8, 'name', 'Kitchen'),
 (1, 'App\\Models\\Variation', 3, 'name', 'Color'), (1, 'App\\Models\\VariationOption', 31, 'name', 'Red'),
 (1, 'App\\Models\\Product', 101, 'name', 'New hot sale 65 pieces'), (11, 'App\\Models\\Product', 101, 'name', 'عرض 65 قطعة'),
 (1, 'App\\Models\\Package', 2, 'name', 'Pro');
INSERT INTO admin_roles (id, name) VALUES (1, 'Super Admin');
INSERT INTO admins (id, role_id, name, email, password, status) VALUES (1, 1, 'Old Admin', 'Old.Admin@legacy.test', '$2y$04$s8nZzMOFV./o2zmcoPdTheBks38ocvZS5ybN/A3k0I/hrAlgUGW52', 'active');
INSERT INTO tenants (id, data, created_at) VALUES ('11111111-2222-3333-4444-555555555555', '{"name": "Legacy Owner", "slug": "legacystore", "email": "Owner@Legacy.test", "phone": "+201000000000", "status": "active", "shop_name": "Legacy Store", "package_id": 2, "country_ids": [52, 150], "category_ids": [7], "profit_percentage": 25, "tenancy_db_name": "{{STORE_DB}}"}', '2026-10-01 10:00:00');
INSERT INTO domains (id, domain, tenant_id) VALUES (1, 'legacystore.dokan-v2.loc', '11111111-2222-3333-4444-555555555555');
INSERT INTO tenant_owners (id, tenant_id, email, password) VALUES (1, '11111111-2222-3333-4444-555555555555', 'owner@legacy.test', '$2y$04$s8nZzMOFV./o2zmcoPdTheBks38ocvZS5ybN/A3k0I/hrAlgUGW52');
INSERT INTO product_tenant_assignments (product_id, tenant_id) VALUES (101, '11111111-2222-3333-4444-555555555555');
INSERT INTO central_coupons (id, code, name, type, value, minimum_spend, active) VALUES (1, 'welcome10', 'Welcome', 'percentage', 10, 50, 1);
INSERT INTO central_coupon_country (central_coupon_id, country_id) VALUES (1, 52);
INSERT INTO central_flash_sales (id, discount_percentage, start_date, end_date, active) VALUES (1, 10, '2026-11-01 00:00:00', '2026-12-31 00:00:00', 1);
INSERT INTO central_flash_sale_product (central_flash_sale_id, product_id) VALUES (1, 101);
INSERT INTO support_tickets (id, tenant_id, subject, status, priority, category) VALUES (1, '11111111-2222-3333-4444-555555555555', 'Payout question', 'open', 'high', 'general');
INSERT INTO support_ticket_messages (id, ticket_id, sender_type, sender_name, body) VALUES (1, 1, 'tenant', 'Legacy Owner', 'When is my payout?');
INSERT INTO affiliates (id, type, tenant_id, name, email, password, code, commission_type, commission_value, balance, total_earned, total_paid, status) VALUES
 (1, 'independent', NULL, 'Aff One', 'aff@legacy.test', '$2y$04$s8nZzMOFV./o2zmcoPdTheBks38ocvZS5ybN/A3k0I/hrAlgUGW52', 'aff1code', 'percentage', 12, 15.50, 40.00, 24.50, 'active');
-- P12 batch 2 (decision #113): after-sales, billing, content, e-mail templates, shipping, settlements/payouts, catalogue requests. 'gone-store' = a store that no longer exists (excluded from reconciliation).
INSERT INTO return_requests (id, tenant_id, order_number, type, customer_id, order_item_id, product_id, product_variant_id, quantity, return_method, reason, description, status, refund_amount, inspection_result, received_at, inspected_at, reviewed_at, created_at, updated_at, product_type, catalog_product_id) VALUES
 (7, '11111111-2222-3333-4444-555555555555', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'return', 1, 40, 1, 11, 1, 'drop_off', 'changed_mind', 'Not needed', 'refunded', 20.22, 'passed', '2026-10-12 10:00:00', '2026-10-13 10:00:00', '2026-10-11 10:00:00', '2026-10-10 10:00:00', '2026-10-14 10:00:00', 'central', 101),
 (8, '11111111-2222-3333-4444-555555555555', 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'return', 1, 41, 1, 11, 1, 'drop_off', 'defective', 'Broken', 'pending', 5.00, NULL, NULL, NULL, NULL, '2026-10-10 10:00:00', '2026-10-10 10:00:00', 'central', 101);
INSERT INTO return_request_notes (id, return_request_id, author_type, note, customer_visible, created_at) VALUES
 (1, 7, 'system', 'Return approved', 1, '2026-10-11 10:00:00'), (2, 7, 'admin', 'Checked the box', 0, '2026-10-13 10:00:00');
INSERT INTO refunds (id, reference, tenant_id, order_number, return_request_id, source, reason, items_amount, shipping_amount, return_fee, amount, gateway, refund_method, status, requested_by_type, approved_by_type, approved_by_name, requested_at, approved_at, processed_at, created_at) VALUES
 (3, 'RF-LEG-1', '11111111-2222-3333-4444-555555555555', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 7, 'return', 'Changed My Mind', 20.22, 0, 0, 20.22, 'stripe', 'manual', 'completed', 'admin', 'admin', 'Old Admin', '2026-10-13 10:00:00', '2026-10-13 11:00:00', '2026-10-14 10:00:00', '2026-10-13 10:00:00'),
 (4, 'RF-LEG-2', '11111111-2222-3333-4444-555555555555', 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee', NULL, 'cancellation', 'Cancelled', 10.00, 0, 0, 10.00, NULL, 'manual', 'pending', 'tenant', NULL, NULL, '2026-10-15 10:00:00', NULL, NULL, '2026-10-15 10:00:00'),
 (5, 'RF-GONE-1', 'gone-store', 'cccccccc-bbbb-4ccc-8ddd-eeeeeeeeeeee', NULL, 'cancellation', 'Cancelled', 5.00, 0, 0, 5.00, NULL, 'manual', 'completed', 'admin', NULL, NULL, '2026-10-15 10:00:00', NULL, NULL, '2026-10-15 10:00:00');
INSERT INTO payment_logs (id, tenant_id, package_id, gateway, amount, status, reference, paid_at, meta, created_at, updated_at) VALUES
 (1, '11111111-2222-3333-4444-555555555555', 2, 'stripe', 299.00, 'paid', 'ch_legacy_1', '2026-10-01 16:16:29', '{"raw": {"source": {"last4": "4242", "fingerprint": "LEGACYFP"}}}', '2026-10-01 16:16:29', '2026-10-01 16:16:29'),
 (2, '11111111-2222-3333-4444-555555555555', 2, 'stripe', 299.00, 'pending', NULL, NULL, NULL, '2026-10-02 16:16:29', '2026-10-02 16:16:29'),
 (3, 'gone-store', 2, 'stripe', 299.00, 'paid', 'ch_gone', '2026-10-01 16:16:29', NULL, '2026-10-01 16:16:29', '2026-10-01 16:16:29');
INSERT INTO invoices (id, tenant_id, tenant_order_id, order_uuid, order_number, invoice_number, currency, amount, status, created_at) VALUES
 (1, '11111111-2222-3333-4444-555555555555', 4, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'INV-LEG-1', 'USD', 183.18, 'paid', '2026-10-05 10:00:00'),
 (2, '11111111-2222-3333-4444-555555555555', 9, 'dddddddd-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'dddddddd-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'INV-LEG-2', 'USD', 100.00, 'paid', '2026-10-05 10:00:00');
INSERT INTO blog_categories (id, slug, status, created_at) VALUES (1, 'news', 'active', '2026-09-01 10:00:00');
INSERT INTO blog_posts (id, blog_category_id, slug, status, published_at, created_at) VALUES (1, 1, 'hello-world', 'published', '2026-09-02 10:00:00', '2026-09-02 10:00:00');
INSERT INTO faqs (id, status, created_at) VALUES (1, 'active', '2026-09-01 10:00:00');
INSERT INTO static_pages (id, slug, status, is_compliance, created_at) VALUES (1, 'about-us', 'active', 0, '2026-09-01 10:00:00'), (2, 'privacy-policy', 'draft', 1, '2026-09-01 10:00:00');
INSERT INTO translations (language_id, translatable_type, translatable_id, field, value) VALUES
 (1, 'App\\Models\\BlogCategory', 1, 'name', 'News'), (11, 'App\\Models\\BlogCategory', 1, 'name', 'أخبار'),
 (1, 'App\\Models\\BlogPost', 1, 'title', 'Hello world'), (11, 'App\\Models\\BlogPost', 1, 'title', 'مرحبا'), (1, 'App\\Models\\BlogPost', 1, 'content', '<p>First post of the old blog.</p>'), (1, 'App\\Models\\BlogPost', 1, 'excerpt', 'First post'),
 (1, 'App\\Models\\Faq', 1, 'question', 'How do I pay?'), (1, 'App\\Models\\Faq', 1, 'answer', 'By card.'), (1, 'App\\Models\\Faq', 1, 'category', 'Billing'),
 (1, 'App\\Models\\StaticPage', 1, 'title', 'About us'), (11, 'App\\Models\\StaticPage', 1, 'title', 'من نحن'), (1, 'App\\Models\\StaticPage', 1, 'content', '<p>We sell.</p>'),
 (1, 'App\\Models\\StaticPage', 2, 'title', 'Privacy'), (1, 'App\\Models\\StaticPage', 2, 'content', '<p>Private.</p>'),
 (1, 'App\\Models\\ShippingZone', 1, 'name', 'KSA Zone');
INSERT INTO email_templates (id, name, action, subject, body, type, status, created_at) VALUES
 (1, 'Order Invoice', 'admin.invoice_issued', 'Invoice {{invoice_number}}', '<p>Invoice</p>', 'admin', 'active', '2026-09-01 10:00:00'),
 (8, 'Customer Order Confirmation', 'tenant.order_confirmation', 'We received your order {{order_number}}', '<div style="color:red"><p>Hi {{customer_name}}, thanks from {{store_name}}.</p><script>x</script></div>', 'tenant', 'active', '2026-09-01 10:00:00'),
 (11, 'Order Delivered Confirmation', 'tenant.order_delivered', 'Delivered {{order_number}}', '<p>Code {{tracking_secret}}</p>', 'tenant', 'active', '2026-09-01 10:00:00');
INSERT INTO email_template_translations (id, email_template_id, locale, subject, body) VALUES (1, 8, 'ar', 'استلمنا طلبك {{order_number}}', '<p>مرحبا {{customer_name}}</p>');
INSERT INTO fixed_shipping_costs (id, country_id, price_per_gram, currency_code, is_active, created_at) VALUES (1, 52, 0.221000, 'USD', 1, '2026-07-18 11:35:15'), (2, 999, 1.000000, 'USD', 1, '2026-07-18 11:35:15');
INSERT INTO shipping_zones (id, branch_id, country_id, name, code, currency_code, status, created_at) VALUES (1, NULL, 150, 'KSA Zone', 'ksa', 'USD', 'active', '2026-06-12 12:47:47'), (2, NULL, NULL, 'Global Zone', 'global', 'USD', 'active', '2026-06-12 12:47:47');
INSERT INTO shipping_zone_rates (id, shipping_zone_id, name, min_weight, max_weight, price, is_active, created_at) VALUES
 (1, 1, 'Light', 0.00, 1000.00, 10.00, 1, '2026-06-12 12:47:47'), (2, 1, 'Heavy', 1001.00, NULL, 25.50, 1, '2026-06-12 12:47:47'), (3, 1, 'Old', 0.00, 10.00, 1.00, 0, '2026-06-12 12:47:47');
INSERT INTO vendor_settlements (id, tenant_id, order_id, order_uuid, invoice_number, tenant_name, product_cost, shipping_cost, gateway_fee, total, gateway_code, transaction_id, status, settled_at, created_at) VALUES
 (1, '11111111-2222-3333-4444-555555555555', 4, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'VS-LEG-1', 'Legacy Owner', 1.76, 200.00, 0, 201.76, 'stripe', 'pi_settle_1', 'paid', '2026-10-20 10:00:00', '2026-10-20 10:00:00'),
 (2, 'gone-store', 9, 'eeeeeeee-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'VS-GONE-1', 'Gone', 1.00, 0, 0, 1.00, 'stripe', NULL, 'paid', '2026-10-20 10:00:00', '2026-10-20 10:00:00');
INSERT INTO tenant_payouts (id, tenant_id, invoice_number, tenant_name, amount, status, currency, method, bank_name, bank_account, transaction_reference, paid_at, created_at, updated_at) VALUES
 (1, '11111111-2222-3333-4444-555555555555', 'PO-LEG-1', 'Legacy Owner', 50.00, 'paid', 'USD', 'bank', 'Bank X', 'EG12 3456 7890 1234', 'TRX-1', '2026-10-25 10:00:00', '2026-10-25 10:00:00', '2026-10-25 10:00:00'),
 (2, '11111111-2222-3333-4444-555555555555', 'PO-LEG-2', 'Legacy Owner', 20.00, 'pending', 'USD', 'bank', NULL, NULL, NULL, NULL, '2026-10-26 10:00:00', '2026-10-26 10:00:00');
INSERT INTO product_requests (id, tenant_id, title, description, product_url, attachments, status, tenant_has_unread, admin_has_unread, last_reply_at, priority, created_at, updated_at) VALUES
 (2, '11111111-2222-3333-4444-555555555555', 'Need mugs', 'Blue mugs please', 'https://example.test/mug', '["product-requests/legacy/a.png"]', 'pending', 0, 1, '2026-11-04 18:44:50', 'high', '2026-11-04 18:44:14', '2026-11-04 18:44:50'),
 (3, 'gone-store', 'Gone', 'Gone', NULL, NULL, 'pending', 0, 1, NULL, 'normal', '2026-11-04 18:44:14', '2026-11-04 18:44:14');
INSERT INTO product_request_messages (id, product_request_id, sender_type, sender_name, body, attachments, created_at) VALUES
 (1, 2, 'tenant', 'vendor', 'Any update?', '["product-requests/legacy/a.png", "product-requests/legacy/missing.png"]', '2026-11-04 18:44:20'),
 (2, 2, 'admin', 'Old Admin', 'Working on it', NULL, '2026-11-04 18:44:50');
INSERT INTO brand_requests (id, tenant_id, title, description, status, admin_notes, created_at) VALUES (1, '11111111-2222-3333-4444-555555555555', 'Acme', 'Brand please', 'approved', 'ok', '2026-11-04 18:44:14');
INSERT INTO brand_request_messages (id, brand_request_id, sender_type, sender_name, message, created_at) VALUES (1, 1, 'admin', 'Old Admin', 'Reviewing', '2026-11-04 19:00:00');
INSERT INTO manufacturing_requests (id, tenant_id, product_id, product_name, description, reference_url, images, quantity, status, created_at) VALUES (3, '11111111-2222-3333-4444-555555555555', 101, 'Mug batch', 'Fifty mugs', 'https://example.test/p/1', NULL, 50, 'pending', '2026-11-04 18:44:14');
INSERT INTO manufacturing_payment_requests (id, manufacturing_request_id, tenant_id, label, amount, currency, status, created_at) VALUES (1, 3, '11111111-2222-3333-4444-555555555555', 'Sample', 55.00, 'USD', 'pending', '2026-11-04 18:44:14');
