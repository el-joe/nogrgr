<?php

namespace App\Modules\Localization\DTOs;

/**
 * Vendor › Settings › Languages save: the default language, the switched-on codes and
 * the storefront switcher order (codes, first = first).
 */
final readonly class StoreLanguageSettingsData
{
    /**
     * @param  list<string>  $enabled
     * @param  list<string>  $order
     */
    public function __construct(
        public string $default,
        public array $enabled,
        public array $order,
    ) {}
}
