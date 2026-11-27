<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\DTOs\Themes\ThemePartData;
use App\Modules\Storefront\Enums\ThemePartType;
use App\Modules\Storefront\Models\ThemePart;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Template parts (ADM-088 / 088a, decision #120): create / edit / delete, one default per
 * theme + type (setting a default also switches it on), active / inactive, and the parts
 * the storefront applies for a theme (default + active, one per type).
 */
class ThemePartService extends BaseService
{
    /**
     * @var array<int, array<string, ThemePart>>
     */
    private array $applied = [];

    public function __construct(private readonly ThemeImageStore $images) {}

    public function create(ThemePartData $data): ThemePart
    {
        $part = new ThemePart(['theme_id' => $data->themeId, 'type' => $data->type, 'sort_order' => (int) ThemePart::query()->where('theme_id', $data->themeId)->max('sort_order') + 1]);

        return $this->fill($part, $data);
    }

    public function update(ThemePart $part, ThemePartData $data): ThemePart
    {
        return $this->fill($part, $data);
    }

    public function setDefault(ThemePart $part): ThemePart
    {
        DB::connection($part->getConnectionName())->transaction(function () use ($part): void {
            ThemePart::query()->where('theme_id', $part->theme_id)->where('type', $part->type)->whereKeyNot($part->id)->update(['is_default' => false]);
            $part->forceFill(['is_default' => true, 'is_active' => true])->save();
        });

        return $part;
    }

    public function setActive(ThemePart $part, bool $active): ThemePart
    {
        $part->forceFill(['is_active' => $active])->save();

        return $part;
    }

    public function delete(ThemePart $part): void
    {
        $path = $part->preview_path;
        $part->delete();
        $this->images->delete($path);
    }

    /**
     * Parts the storefront applies for a theme: type value => the default active part.
     *
     * @return array<string, ThemePart>
     */
    public function appliedFor(ResolvedThemeData $theme): array
    {
        if ($theme->id === null) {
            return [];
        }

        return $this->applied[$theme->id] ??= ThemePart::query()
            ->where('theme_id', $theme->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->get()
            ->keyBy(static fn (ThemePart $part): string => $part->type->value)
            ->all();
    }

    public function applied(ResolvedThemeData $theme, ThemePartType $type): ?ThemePart
    {
        return $this->appliedFor($theme)[$type->value] ?? null;
    }

    private function fill(ThemePart $part, ThemePartData $data): ThemePart
    {
        $old = $part->preview_path;
        $path = $old;

        if ($data->preview !== null) {
            $path = $this->images->store($data->preview, 'parts');
        } elseif ($data->removePreview) {
            $path = null;
        }

        $part->fill([
            'theme_id' => $data->themeId,
            'type' => $data->type,
            'name' => $data->name,
            'settings' => $data->settings,
            'preview_path' => $path,
            'is_active' => $data->isActive || $data->isDefault,
        ])->save();

        if ($data->isDefault) {
            $this->setDefault($part);
        } elseif ($part->is_default) {
            $part->forceFill(['is_default' => false])->save();
        }

        if ($old !== $path) {
            $this->images->delete($old);
        }

        return $part->refresh();
    }
}
