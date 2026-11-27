
DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `central_language_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `native_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direction` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ltr',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `translation_progress` tinyint unsigned NOT NULL DEFAULT '0',
  `translation_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `translation_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_translation_token_count` int unsigned NOT NULL DEFAULT '0',
  `last_translation_cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `image_file_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`),
  KEY `languages_central_language_id_index` (`central_language_id`),
  KEY `languages_image_file_id_index` (`image_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `translations`;
CREATE TABLE `translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint unsigned NOT NULL,
  `translatable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `translatable_id` bigint unsigned NOT NULL,
  `field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_translations_unique_field_per_language` (`language_id`,`translatable_type`,`translatable_id`,`field`),
  KEY `translations_translatable_type_translatable_id_index` (`translatable_type`,`translatable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `central_product_id` bigint unsigned DEFAULT NULL,
  `has_custom_translations` tinyint(1) NOT NULL DEFAULT '0',
  `needs_ai_translation` tinyint(1) NOT NULL DEFAULT '0',
  `ai_translated_at` timestamp NULL DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `central_visible` tinyint(1) NOT NULL DEFAULT '1',
  `allowed_country_ids` json DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `order_number` int unsigned NOT NULL DEFAULT '0',
  `return_policy_override` tinyint(1) NOT NULL DEFAULT '0',
  `is_returnable` tinyint(1) NOT NULL DEFAULT '1',
  `return_window_days` smallint unsigned DEFAULT NULL,
  `return_fee` decimal(8,2) DEFAULT NULL,
  `return_video_required` tinyint(1) NOT NULL DEFAULT '0',
  `return_conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_own_product` tinyint(1) NOT NULL DEFAULT '0',
  `is_tenant_owned` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True when the product was individually assigned from central to this tenant — central profit is shipping only',
  `requires_shipping` tinyint(1) NOT NULL DEFAULT '1',
  `stock` int DEFAULT NULL,
  `orders_count` int unsigned NOT NULL DEFAULT '0',
  `social_posts` json DEFAULT NULL,
  `social_image_b64` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ai_price_data` json DEFAULT NULL,
  `fixed_shipping_costs` json DEFAULT NULL,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `min_stock` int NOT NULL DEFAULT '0',
  `manage_stock` tinyint(1) NOT NULL DEFAULT '1',
  `is_taxable` tinyint(1) NOT NULL DEFAULT '1',
  `weight_grams` int DEFAULT NULL,
  `price` json DEFAULT NULL,
  `profit` json DEFAULT NULL,
  `default_price` decimal(20,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `products_central_product_id_index` (`central_product_id`),
  KEY `products_slug_index` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_grams` int DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `central_product_variant_id` bigint unsigned DEFAULT NULL,
  `option_ids` json DEFAULT NULL,
  `real_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock` int DEFAULT NULL,
  `margin_percentage` decimal(6,2) NOT NULL DEFAULT '0.00',
  `thumbnail_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sell_price` json DEFAULT NULL,
  `profit` json DEFAULT NULL,
  `default_sell_price` decimal(20,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `product_variants_product_id_foreign` (`product_id`),
  KEY `product_variants_central_product_variant_id_index` (`central_product_variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` bigint unsigned DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `language` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` bigint unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_addresses_customer_id_foreign` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_group_uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `shipping_address` json DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_by_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_by_id` bigint unsigned DEFAULT NULL,
  `stock_deducted_at` timestamp NULL DEFAULT NULL,
  `stock_restored_at` timestamp NULL DEFAULT NULL,
  `refunded_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `refunded_at` timestamp NULL DEFAULT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  `payout_released` tinyint(1) NOT NULL DEFAULT '0',
  `payment_details` json DEFAULT NULL,
  `discount_id` bigint unsigned DEFAULT NULL,
  `discount_percentage` decimal(6,2) NOT NULL DEFAULT '0.00',
  `coupon_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `coupon_source` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_percentage` decimal(6,2) NOT NULL DEFAULT '0.00',
  `shipping_zone_rate_id` bigint unsigned DEFAULT NULL,
  `shipping_charge` decimal(10,2) NOT NULL DEFAULT '0.00',
  `platform_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vendor_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vendor_gateway_id` bigint unsigned DEFAULT NULL COMMENT 'Central payment gateway selected by vendor to pay central',
  `vendor_gateway_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Gateway transaction fee applied when vendor pays central',
  `vendor_settled_at` timestamp NULL DEFAULT NULL,
  `tracking_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_settlement_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_gateway_id` bigint unsigned DEFAULT NULL,
  `collected_by` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_uuid_unique` (`uuid`),
  KEY `orders_customer_id_foreign` (`customer_id`),
  KEY `orders_discount_id_index` (`discount_id`),
  KEY `orders_payment_gateway_id_index` (`payment_gateway_id`),
  KEY `orders_shipping_zone_rate_id_index` (`shipping_zone_rate_id`),
  KEY `orders_vendor_gateway_id_index` (`vendor_gateway_id`),
  KEY `orders_vendor_settled_at_index` (`vendor_settled_at`),
  KEY `orders_order_group_uuid_index` (`order_group_uuid`),
  KEY `orders_cancelled_at_index` (`cancelled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_variant_id` bigint unsigned DEFAULT NULL,
  `qty` int unsigned NOT NULL DEFAULT '1',
  `cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `profit_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sub_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fixed_shipping` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_source` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `weight` decimal(10,3) NOT NULL DEFAULT '0.000',
  `grand_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `order_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `order_activities`;
CREATE TABLE `order_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_activities_order_id_foreign` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_rates`;
CREATE TABLE `product_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `stars` tinyint unsigned NOT NULL DEFAULT '5',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_rates_product_id_foreign` (`product_id`),
  KEY `product_rates_customer_id_foreign` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `favorites_customer_id_product_id_unique` (`customer_id`,`product_id`),
  KEY `favorites_product_id_foreign` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `central_coupon_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `minimum_spend` decimal(10,2) NOT NULL DEFAULT '0.00',
  `country_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`),
  KEY `coupons_central_coupon_id_index` (`central_coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `flash_sales`;
CREATE TABLE `flash_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `central_flash_sale_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `discount_percentage` decimal(6,2) NOT NULL DEFAULT '0.00',
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `banner_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` bigint unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sales_product_id_foreign` (`product_id`),
  KEY `flash_sales_central_flash_sale_id_index` (`central_flash_sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `flash_sale_product`;
CREATE TABLE `flash_sale_product` (
  `flash_sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`flash_sale_id`,`product_id`),
  KEY `flash_sale_product_product_id_foreign` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `tenant_product_overrides` (
  `central_product_id` bigint unsigned NOT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT NULL,
  `order_number` int unsigned DEFAULT NULL,
  `profit` json DEFAULT NULL,
  `weight_grams_override` int unsigned DEFAULT NULL,
  `adopted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`central_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `tenant_product_variant_overrides` (
  `central_variant_id` bigint unsigned NOT NULL,
  `central_product_id` bigint unsigned NOT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `profit` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`central_variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `tenant_field_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `field` varchar(255) NOT NULL,
  `locale` varchar(255) DEFAULT NULL,
  `locale_key` varchar(255) NOT NULL,
  `value` longtext,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
