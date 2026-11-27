<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Images of page-builder sections (image + text) on the store's tenant `public` disk,
 * served from /tenancy/assets/ like store banners.
 */
class PageBuilderImageStore
{
    public const DIRECTORY = 'storefront/page-builder';

    public function store(UploadedFile $file): string
    {
        $path = $file->store(self::DIRECTORY, 'public');

        if (! is_string($path)) {
            throw new LogicException('The image could not be stored.');
        }

        return $path;
    }

    public static function url(string $path): string
    {
        $tenant = tenant();

        return $tenant instanceof Tenant
            ? StoreSettingsService::storeUrl($tenant).'/tenancy/assets/'.$path
            : Storage::disk('public')->url($path);
    }
}
