<?php

namespace App\Modules\Ai\Repositories\Eloquent;

use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Enums\TranslationItemStatus;
use App\Modules\Ai\Models\StoreTranslationItem;
use App\Modules\Ai\Repositories\Contracts\StoreTranslationItemRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends BaseRepository<StoreTranslationItem>
 */
class StoreTranslationItemRepository extends BaseRepository implements StoreTranslationItemRepositoryContract
{
    protected function model(): string
    {
        return StoreTranslationItem::class;
    }

    public function doneHashes(StoreTranslationSection $section, string $locale, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        /** @var array<string, string> $hashes */
        $hashes = $this->query()
            ->where('section', $section->value)
            ->where('locale', $locale)
            ->where('status', TranslationItemStatus::Done->value)
            ->whereIn('item_key', $keys)
            ->pluck('source_hash', 'item_key')
            ->map(static fn (mixed $hash): string => (string) $hash)
            ->all();

        return $hashes;
    }

    public function markDone(int $runId, StoreTranslationSection $section, string $key, string $locale, string $hash): void
    {
        $this->upsertItem($runId, $section, $key, $locale, [
            'status' => TranslationItemStatus::Done->value,
            'source_hash' => $hash,
            'error' => null,
            'translated_at' => now(),
        ]);
    }

    public function markFailed(int $runId, StoreTranslationSection $section, string $key, string $locale, string $error): void
    {
        $this->upsertItem($runId, $section, $key, $locale, [
            'status' => TranslationItemStatus::Failed->value,
            'error' => Str::limit($error, 490),
        ]);
    }

    public function countsBySection(string $locale): array
    {
        $counts = [];

        $rows = $this->query()
            ->where('locale', $locale)
            ->select('section', 'status', DB::raw('count(*) as total'))
            ->groupBy('section', 'status')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $counts[$row->section] ??= ['done' => 0, 'failed' => 0];
            $counts[$row->section][$row->status === TranslationItemStatus::Done->value ? 'done' : 'failed'] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertItem(int $runId, StoreTranslationSection $section, string $key, string $locale, array $attributes): void
    {
        $item = $this->query()->firstOrNew(['section' => $section->value, 'item_key' => $key, 'locale' => $locale]);
        $item->fill($attributes + ['run_id' => $runId]);
        $item->attempts = $item->attempts + 1;
        $item->save();
    }
}
