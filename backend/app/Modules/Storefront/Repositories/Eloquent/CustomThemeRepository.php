<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\CustomTheme;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<CustomTheme>
 */
class CustomThemeRepository extends BaseRepository implements CustomThemeRepositoryContract
{
    /**
     * @var array<int, CustomThemeVersion|null>
     */
    private array $live = [];

    protected function model(): string
    {
        return CustomTheme::class;
    }

    public function forTenant(int $tenantId): ?CustomTheme
    {
        return CustomTheme::query()->forTenant($tenantId)->first();
    }

    public function firstOrCreateForTenant(int $tenantId, string $name): CustomTheme
    {
        return $this->forTenant($tenantId) ?? CustomTheme::query()->create(['tenant_id' => $tenantId, 'name' => $name]);
    }

    public function versions(CustomTheme $theme): Collection
    {
        return CustomThemeVersion::query()->forTenant($theme->tenant_id)->where('custom_theme_id', $theme->id)->orderByDesc('id')->get();
    }

    public function findVersion(int $id): ?CustomThemeVersion
    {
        return CustomThemeVersion::query()->with('theme')->find($id);
    }

    public function approvedForTenant(int $tenantId): Collection
    {
        return CustomThemeVersion::query()->forTenant($tenantId)->where('status', CustomThemeVersionStatus::Approved->value)->orderByDesc('id')->get();
    }

    public function liveVersionForTenant(int $tenantId): ?CustomThemeVersion
    {
        if (array_key_exists($tenantId, $this->live)) {
            return $this->live[$tenantId];
        }

        $theme = CustomTheme::query()->forTenant($tenantId)->where('is_active', true)->whereNotNull('live_version_id')->first();
        $version = $theme !== null ? CustomThemeVersion::query()->forTenant($tenantId)->whereKey($theme->live_version_id)->where('status', CustomThemeVersionStatus::Approved->value)->first() : null;
        $version?->setRelation('theme', $theme);

        return $this->live[$tenantId] = $version;
    }

    public function previousApproved(CustomThemeVersion $version): ?CustomThemeVersion
    {
        $theme = $version->theme;

        if ($theme->live_version_id !== null && $theme->live_version_id !== $version->id) {
            $live = CustomThemeVersion::query()->forTenant($version->tenant_id)->whereKey($theme->live_version_id)->first();

            if ($live !== null) {
                return $live;
            }
        }

        return CustomThemeVersion::query()->forTenant($version->tenant_id)
            ->where('custom_theme_id', $version->custom_theme_id)
            ->where('status', CustomThemeVersionStatus::Approved->value)
            ->where('id', '<', $version->id)
            ->orderByDesc('id')
            ->first();
    }

    public function reviewQuery(): Builder
    {
        return CustomThemeVersion::withoutTenantScope()
            ->with(['theme', 'tenant'])
            ->whereIn('status', [CustomThemeVersionStatus::PendingReview->value, CustomThemeVersionStatus::Approved->value, CustomThemeVersionStatus::Rejected->value]);
    }

    public function createVersion(CustomTheme $theme, array $attributes): CustomThemeVersion
    {
        $version = CustomThemeVersion::query()->create($attributes + ['tenant_id' => $theme->tenant_id, 'custom_theme_id' => $theme->id]);
        $version->setRelation('theme', $theme);

        return $version;
    }

    public function updateVersion(CustomThemeVersion $version, array $attributes): CustomThemeVersion
    {
        $version->forceFill($attributes)->save();
        $this->forget();

        return $version;
    }

    public function deleteVersion(CustomThemeVersion $version): void
    {
        $version->delete();
        $this->forget();
    }

    public function forget(): void
    {
        $this->live = [];
    }
}
