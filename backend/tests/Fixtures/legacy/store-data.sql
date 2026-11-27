INSERT INTO languages (id, name, code, native_name, direction, is_default, is_active) VALUES (1, 'English', 'en', 'English', 'ltr', 1, 1);
INSERT INTO products (id, central_product_id, slug, active, featured, order_number, is_own_product, requires_shipping, stock, profit, default_price, created_at) VALUES
 (1, 101, NULL, 1, 1, 3, 0, 1, NULL, '{"default": {"profit_type": "percentage", "profit_value": 25}, "52": {"profit_type": "fixed", "profit_value": 0.44}}', 0, '2026-10-02 10:00:00'),
 (2, NULL, 'own-mug', 1, 0, 0, 1, 1, 5, NULL, 15.00, '2026-10-02 10:00:00');
INSERT INTO product_variants (id, product_id, central_product_variant_id, title, real_price, sell_price, stock, active, profit) VALUES
 (11, 1, 1001, 'Red', 1.76, '{"default": 2.20}', NULL, 1, '{"52": {"profit_type": "fixed", "profit_value": 0.44}}');
INSERT INTO translations (language_id, translatable_type, translatable_id, field, value) VALUES (1, 'App\\Models\\Tenant\\Product', 2, 'name', 'Own Mug');
INSERT INTO tenant_field_overrides (entity_type, entity_id, field, locale, locale_key, value, approved_at) VALUES ('product', 101, 'name', 'en', 'en', 'Store name for hot sale', '2026-11-01 00:00:00');
INSERT INTO customers (id, full_name, email, phone, password, active, country_id, created_at) VALUES (1, 'Sara Buyer', 'Sara@Buyer.test', '+201111111111', '$2y$04$s8nZzMOFV./o2zmcoPdTheBks38ocvZS5ybN/A3k0I/hrAlgUGW52', 1, 52, '2026-10-03 10:00:00');
INSERT INTO customer_addresses (id, customer_id, full_name, phone, address_line_1, city, is_default, country_id) VALUES
 (1, 1, 'Sara Buyer', '+201111111111', '1 Nile St', 'Cairo', 1, 52), (2, 1, 'Sara Buyer', NULL, '', NULL, 0, NULL);
INSERT INTO orders (id, uuid, customer_id, shipping_address, payment_method, status, paid, payment_details, shipping_charge, coupon_discount, platform_profit, vendor_profit, collected_by, created_at, updated_at) VALUES
 (4, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 1, '{"full_name": "Sara Buyer", "email": "sara@buyer.test", "phone": "+201111111111", "country_id": 52, "city": "Cairo"}', 'stripe', 'delivered', 1, '{"transaction_id": "pi_legacy_4"}', 1.20, 0, 182.74, 0.44, 'platform', '2026-10-05 10:00:00', '2026-10-09 10:00:00');
INSERT INTO order_items (id, order_id, product_id, product_variant_id, qty, cost, profit_amount, price, sub_total, fixed_shipping, discount, discount_source, tax, shipping_fee, weight, grand_total) VALUES
 (40, 4, 1, 11, 1, 1.76, 0.44, 2.20, 2.20, 200.00, 20.22, 'central', 0, 0, 0.25, 181.98);
INSERT INTO order_activities (order_id, title, status, created_at) VALUES (4, 'Order delivered', 'delivered', '2026-10-09 10:00:00');
INSERT INTO product_rates (id, product_id, customer_id, stars, comment, created_at) VALUES (1, 1, 1, 5, 'Great', '2026-10-10 10:00:00');
INSERT INTO favorites (id, customer_id, product_id) VALUES (1, 1, 2);
INSERT INTO coupons (id, code, type, value, minimum_spend, central_coupon_id) VALUES (1, 'store5', 'fixed', 5, 20, NULL), (2, 'welcome10', 'percentage', 10, 50, 1);
