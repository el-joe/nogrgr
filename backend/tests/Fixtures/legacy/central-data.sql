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
INSERT INTO product_labels (id, text, translations, active, sort_order) VALUES (5, 'Hot', '{"en":"Hot","ar":"رائج"}', 1, 0);
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
