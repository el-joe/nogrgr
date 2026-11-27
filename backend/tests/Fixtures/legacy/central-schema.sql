
DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `native_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ltr',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `image_file_id` bigint unsigned DEFAULT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT '1',
  `price` decimal(10,2) DEFAULT NULL,
  `ai_translation_price` decimal(10,2) DEFAULT NULL,
  `countries` json DEFAULT NULL COMMENT 'ISO 3166-1 alpha-2 country codes recommended for this language',
  `translation_progress` tinyint unsigned NOT NULL DEFAULT '100',
  `translation_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `translation_source_locale` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `translation_error` text COLLATE utf8mb4_unicode_ci,
  `ai_tokens_used` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`),
  KEY `languages_image_file_id_index` (`image_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sign` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_rate` decimal(20,6) NOT NULL DEFAULT '1.000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `iso2` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso3` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_code` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_direction` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ltr',
  `phone_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flag_emoji` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active_for_tenants` tinyint(1) NOT NULL DEFAULT '1',
  `is_free` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_language_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso2_unique` (`iso2`),
  UNIQUE KEY `countries_iso3_unique` (`iso3`),
  KEY `countries_default_language_id_foreign` (`default_language_id`),
  KEY `countries_currency_code_index` (`currency_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `cities`;
CREATE TABLE `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `packages`;
CREATE TABLE `packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `features` json DEFAULT NULL,
  `categories_count` int unsigned NOT NULL DEFAULT '0' COMMENT '0 = unlimited (all categories); N = max parent categories tenant may choose at registration',
  `products_limit` int NOT NULL DEFAULT '-1',
  `banners_limit` int NOT NULL DEFAULT '-1',
  `languages_limit` int NOT NULL DEFAULT '-1',
  `orders_per_month_limit` int NOT NULL DEFAULT '-1',
  `ai_calls_limit` int NOT NULL DEFAULT '-1',
  `image_searches_limit` int NOT NULL DEFAULT '-1',
  `ai_translation_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `trial_days` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'orders' COMMENT 'orders = storefront customer payments | vendor_payments = vendor-to-central payments',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inactive',
  `mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'test',
  `credentials` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transaction_fee_percentage` decimal(7,4) NOT NULL DEFAULT '0.0000' COMMENT 'Gateway percentage fee, e.g. 3.75 = 3.75%',
  `transaction_fee_fixed` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Gateway flat fee per transaction, e.g. 0.50 = $0.50',
  `logo_file_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateways_code_type_unique` (`code`,`type`),
  KEY `payment_gateways_logo_file_id_foreign` (`logo_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` bigint unsigned DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `order_number` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_external_id_live_unique` ((if((`deleted_at` is null),`external_id`,NULL))),
  KEY `categories_parent_id_foreign` (`parent_id`),
  KEY `categories_external_id_index` (`external_id`)
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
  UNIQUE KEY `translations_unique_field_per_language` (`language_id`,`translatable_type`,`translatable_id`,`field`),
  KEY `translations_translatable_type_translatable_id_index` (`translatable_type`,`translatable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` bigint unsigned DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned DEFAULT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` bigint unsigned DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `files_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `files_key_index` (`key`),
  KEY `files_external_id_index` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `variations`;
CREATE TABLE `variations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `variation_options`;
CREATE TABLE `variation_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` bigint unsigned DEFAULT NULL,
  `variation_id` bigint unsigned NOT NULL,
  `swatch` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `variation_options_external_id_live_unique` ((if((`deleted_at` is null),`external_id`,NULL))),
  KEY `variation_options_variation_id_index` (`variation_id`),
  KEY `variation_options_external_id_index` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` bigint unsigned DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `weight_grams` int unsigned DEFAULT NULL,
  `fixed_shipping_costs` json DEFAULT NULL,
  `factory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_scope` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all_zones',
  `base_price` decimal(20,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(20,2) DEFAULT NULL,
  `cost_price` decimal(20,2) DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `out_of_stock_at` timestamp NULL DEFAULT NULL,
  `sold_count` int unsigned NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '0',
  `manage_stock` tinyint(1) NOT NULL DEFAULT '1',
  `is_taxable` tinyint(1) NOT NULL DEFAULT '1',
  `requires_shipping` tinyint(1) NOT NULL DEFAULT '1',
  `published_at` timestamp NULL DEFAULT NULL,
  `order_number` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  UNIQUE KEY `products_external_id_unique` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_grams` int unsigned DEFAULT NULL,
  `fixed_shipping_costs` json DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `position` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_live_unique` ((if((`deleted_at` is null),`sku`,NULL))),
  KEY `product_variants_product_id_foreign` (`product_id`),
  KEY `product_variants_sku_index` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_variant_option`;
CREATE TABLE `product_variant_option` (
  `product_variant_id` bigint unsigned NOT NULL,
  `variation_option_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_variant_id`,`variation_option_id`),
  KEY `product_variant_option_variation_option_id_foreign` (`variation_option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_variation`;
CREATE TABLE `product_variation` (
  `product_id` bigint unsigned NOT NULL,
  `variation_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`,`variation_id`),
  KEY `product_variation_variation_id_foreign` (`variation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `category_product`;
CREATE TABLE `category_product` (
  `category_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`category_id`,`product_id`),
  KEY `category_product_product_id_foreign` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_label_product`;
CREATE TABLE `product_label_product` (
  `product_label_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_label_id`,`product_id`),
  UNIQUE KEY `product_label_product_product_id_unique` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_badge_product`;
CREATE TABLE `product_badge_product` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_badge_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `country_id` bigint unsigned DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pbp_unique` (`product_badge_id`,`product_id`,`country_id`),
  KEY `pbp_badge_id_index` (`product_badge_id`),
  KEY `pbp_product_id_index` (`product_id`),
  KEY `product_badge_product_country_id_foreign` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_labels`;
CREATE TABLE `product_labels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translations` json DEFAULT NULL,
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_badges`;
CREATE TABLE `product_badges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_badges_text_unique` (`text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_tenant_assignments`;
CREATE TABLE `product_tenant_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `tenant_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_tenant_assignments_product_id_tenant_id_unique` (`product_id`,`tenant_id`),
  KEY `product_tenant_assignments_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `product_country`;
CREATE TABLE `product_country` (
  `product_id` bigint unsigned NOT NULL,
  `country_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`product_id`,`country_id`),
  KEY `product_country_country_id_foreign` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`),
  KEY `admins_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `permissions_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `data` json DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `domains`;
CREATE TABLE `domains` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domains_domain_unique` (`domain`),
  KEY `domains_tenant_id_foreign` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `tenant_owners`;
CREATE TABLE `tenant_owners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `selected_tenant_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_owners_email_unique` (`email`),
  KEY `tenant_owners_tenant_id_foreign` (`tenant_id`),
  KEY `tenant_owners_selected_tenant_id_foreign` (`selected_tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `central_coupons`;
CREATE TABLE `central_coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `minimum_spend` decimal(10,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `central_coupons_code_live_unique` ((if((`deleted_at` is null),`code`,NULL))),
  KEY `central_coupons_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `central_coupon_country`;
CREATE TABLE `central_coupon_country` (
  `central_coupon_id` bigint unsigned NOT NULL,
  `country_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`central_coupon_id`,`country_id`),
  KEY `central_coupon_country_country_id_foreign` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `central_flash_sales`;
CREATE TABLE `central_flash_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `discount_percentage` decimal(6,2) NOT NULL DEFAULT '0.00',
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `banner_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `central_flash_sale_product`;
CREATE TABLE `central_flash_sale_product` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `central_flash_sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `central_flash_sale_product_central_flash_sale_id_foreign` (`central_flash_sale_id`),
  KEY `central_flash_sale_product_product_id_foreign` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `central_flash_sale_country`;
CREATE TABLE `central_flash_sale_country` (
  `central_flash_sale_id` bigint unsigned NOT NULL,
  `country_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`central_flash_sale_id`,`country_id`),
  KEY `central_flash_sale_country_country_id_foreign` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `tenant_has_unread` tinyint(1) NOT NULL DEFAULT '0',
  `admin_has_unread` tinyint(1) NOT NULL DEFAULT '0',
  `last_reply_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_tickets_tenant_id_index` (`tenant_id`),
  KEY `support_tickets_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `support_ticket_messages`;
CREATE TABLE `support_ticket_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `sender_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachments` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_messages_ticket_id_foreign` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `affiliates`;
CREATE TABLE `affiliates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'external',
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `commission_type` enum('fixed','percentage') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `commission_value` decimal(10,2) NOT NULL DEFAULT '10.00',
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_earned` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','active','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paypal_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_iban` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `affiliates_email_unique` (`email`),
  UNIQUE KEY `affiliates_code_unique` (`code`),
  UNIQUE KEY `affiliates_tenant_id_unique` (`tenant_id`),
  KEY `affiliates_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
