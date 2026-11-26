<?php

namespace App\Modules\Platform\DTOs\Dashboard;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor dashboard live store preview (VEN-009f): one signed theme-preview link per
 * scope ("default" or a selling country id) and the "Edit your storefront" shortcuts.
 */
final class StorefrontPreviewData extends BaseDTO
{
    /**
     * @param  array<string, string>  $scopes  scope => label
     * @param  array<string, string>  $previewUrls  scope => vendor preview route (redirects to the signed storefront link)
     * @param  list<array{label: string, icon: string, url: string}>  $links
     */
    public function __construct(
        public readonly string $storeUrl,
        public readonly string $storeHost,
        public readonly array $scopes,
        public readonly array $previewUrls,
        public readonly string $currentScope,
        public readonly array $links,
    ) {}

    public function hasPreview(): bool
    {
        return isset($this->previewUrls[$this->currentScope]);
    }
}
