<?php

namespace App\Modules\Localization\Services\StaticTexts;

use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Repositories\Contracts\TenantStaticTextRepositoryContract;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use Illuminate\Support\Collection;
use LogicException;

/**
 * The store's side of its storefront static texts (service API for the P8 vendor
 * editor): list per locale, edit (marks the row manual + locked so sync and AI never
 * overwrite it), lock / unlock, and reset to the base value. Keys the platform locked
 * (ADM-093) cannot be edited. Every write busts the store's cache.
 */
class TenantStaticTextService
{
    public function __construct(
        private readonly TenantStaticTextRepositoryContract $texts,
        private readonly StaticTextCatalog $catalog,
        private readonly StaticTextResolver $resolver,
        private readonly TranslationKeyLockService $locks,
    ) {}

    /**
     * Every base key with the store's value in $locale.
     *
     * @return Collection<int, array{key: string, value: string|null, base: string|null, source: StaticTextSource|null, is_locked: bool, platform_locked: bool}>
     */
    public function list(string $locale, ?string $search = null): Collection
    {
        $this->ensureTenancy();
        $rows = $this->texts->keyedFor($locale);
        $needle = $search !== null && trim($search) !== '' ? mb_strtolower(trim($search)) : null;

        $items = collect($this->catalog->keys())
            ->map(function (string $key) use ($rows, $locale): array {
                /** @var TenantStaticText|null $row */
                $row = $rows->get($key);

                return [
                    'key' => $key,
                    'value' => $row?->value,
                    'base' => $this->catalog->value($key, $locale) ?? $this->catalog->value($key, StaticTextCatalog::FALLBACK),
                    'source' => $row?->source,
                    'is_locked' => (bool) $row?->is_locked,
                    'platform_locked' => $this->locks->isLocked(StaticTextCatalog::FILE, $key),
                ];
            });

        if ($needle !== null) {
            $items = $items->filter(fn (array $item): bool => str_contains(mb_strtolower($item['key'].' '.$item['value'].' '.$item['base']), $needle));
        }

        return $items->values();
    }

    public function update(string $key, string $locale, string $value): TenantStaticText
    {
        $this->ensureEditable($key);
        $row = $this->texts->findEntry($key, $locale);
        $attributes = [
            'value' => $value,
            'source' => StaticTextSource::Manual,
            'is_locked' => true,
            'source_hash' => $this->catalog->hash($key, $locale),
        ];

        /** @var TenantStaticText $row */
        $row = $row === null
            ? $this->texts->create($attributes + ['key' => $key, 'locale' => $locale])
            : $this->texts->update($row, $attributes);

        $this->resolver->forget($locale);

        return $row;
    }

    /**
     * Keep the current value from sync / AI overwrites.
     */
    public function lock(string $key, string $locale): TenantStaticText
    {
        $this->ensureEditable($key);
        $row = $this->texts->findEntry($key, $locale) ?? throw TranslationRuleException::unknownKey($key);

        /** @var TenantStaticText $row */
        $row = $this->texts->update($row, ['is_locked' => true]);
        $this->resolver->forget($locale);

        return $row;
    }

    /**
     * Let sync (base locales) or the AI run (other locales) update the value again.
     */
    public function unlock(string $key, string $locale): TenantStaticText
    {
        $this->ensureEditable($key);
        $row = $this->texts->findEntry($key, $locale) ?? throw TranslationRuleException::unknownKey($key);
        $source = in_array($locale, StaticTextCatalog::BASE_LOCALES, true) ? StaticTextSource::Base : StaticTextSource::Ai;

        /** @var TenantStaticText $row */
        $row = $this->texts->update($row, ['is_locked' => false, 'source' => $source]);
        $this->resolver->forget($locale);

        return $row;
    }

    /**
     * Back to the platform text: base locales get the base value, other locales drop the
     * row (English fallback until the AI run translates it again).
     */
    public function reset(string $key, string $locale): ?TenantStaticText
    {
        $this->ensureEditable($key);
        $row = $this->texts->findEntry($key, $locale);
        $base = in_array($locale, StaticTextCatalog::BASE_LOCALES, true) ? $this->catalog->value($key, $locale) : null;

        if ($base === null) {
            if ($row !== null) {
                $this->texts->delete($row);
            }

            $this->resolver->forget($locale);

            return null;
        }

        $attributes = ['value' => $base, 'source' => StaticTextSource::Base, 'is_locked' => false, 'source_hash' => sha1($base)];

        /** @var TenantStaticText $row */
        $row = $row === null
            ? $this->texts->create($attributes + ['key' => $key, 'locale' => $locale])
            : $this->texts->update($row, $attributes);

        $this->resolver->forget($locale);

        return $row;
    }

    private function ensureEditable(string $key): void
    {
        $this->ensureTenancy();

        if (! $this->catalog->has($key)) {
            throw TranslationRuleException::unknownKey($key);
        }

        if ($this->locks->isLocked(StaticTextCatalog::FILE, $key)) {
            throw TranslationRuleException::lockedByPlatform($key);
        }
    }

    private function ensureTenancy(): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Tenancy must be initialized to manage store static texts.');
        }
    }
}
