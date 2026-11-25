<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Store settings of the current tenant (tenant DB `store_settings`). The logo lives on
 * the tenant-suffixed `public` disk and is served by stancl's tenant asset route on the
 * store's own host, so its URL is valid from both vendor panel host modes and the
 * storefront.
 */
class StoreSettingsService extends BaseService
{
    public const LOGO_DIRECTORY = 'branding';

    public function __construct(private readonly StoreSettingRepositoryContract $settings) {}

    public function logoPath(): ?string
    {
        $path = $this->settings->get(StoreSettingKey::LogoPath);

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function logoUrl(): ?string
    {
        $path = $this->logoPath();
        $tenant = tenant();

        if ($path === null || ! $tenant instanceof Tenant) {
            return null;
        }

        return self::storeUrl($tenant).'/tenancy/assets/'.$path;
    }

    /**
     * Replaces the store logo (PNG / JPG / WebP / SVG, validated by the FormRequest).
     */
    public function replaceLogo(UploadedFile $file): string
    {
        if (! tenancy()->initialized) {
            throw new LogicException('The store logo is written inside tenant context.');
        }

        $disk = Storage::disk('public');
        $previous = $this->logoPath();
        $path = $file->storeAs(self::LOGO_DIRECTORY, 'logo-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.strtolower($file->getClientOriginalExtension() ?: 'png'), 'public');

        if (! is_string($path)) {
            throw new LogicException('The store logo could not be stored.');
        }

        $this->settings->put(StoreSettingKey::LogoPath, $path);

        if ($previous !== null && $previous !== $path) {
            $disk->delete($previous);
        }

        return $path;
    }

    public function timestamp(StoreSettingKey $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function stamp(StoreSettingKey $key): void
    {
        $this->settings->put($key, now()->toIso8601String());
    }

    public function clear(StoreSettingKey $key): void
    {
        $this->settings->forget($key);
    }

    /**
     * Public storefront base URL of a store (its default sub-domain).
     */
    public static function storeUrl(Tenant $tenant): string
    {
        $scheme = app()->runningInConsole() && ! app()->runningUnitTests() ? 'https' : request()->getScheme();

        return $scheme.'://'.$tenant->subdomainHost();
    }
}
