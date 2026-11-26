<?php

namespace App\Modules\Ai\Repositories\Eloquent;

use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Enums\AdminTranslationRunStatus;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Ai\Repositories\Contracts\AdminTranslationRunRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<AdminTranslationRun>
 */
class AdminTranslationRunRepository extends BaseRepository implements AdminTranslationRunRepositoryContract
{
    protected function model(): string
    {
        return AdminTranslationRun::class;
    }

    public function active(AdminTranslationKind $kind, string $locale): ?AdminTranslationRun
    {
        return $this->query()
            ->where('kind', $kind->value)
            ->where('locale', $locale)
            ->whereIn('status', [AdminTranslationRunStatus::Queued->value, AdminTranslationRunStatus::Translating->value])
            ->latest('id')
            ->first();
    }

    public function latest(AdminTranslationKind $kind, ?string $locale = null): ?AdminTranslationRun
    {
        return $this->query()
            ->where('kind', $kind->value)
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->latest('id')
            ->first();
    }

    public function advance(int $runId, string $section, int $done, int $failed): ?AdminTranslationRun
    {
        $model = $this->query()->getModel();

        return DB::connection($model->getConnectionName())->transaction(function () use ($runId, $section, $done, $failed): ?AdminTranslationRun {
            $run = $this->query()->lockForUpdate()->find($runId);

            if (! $run instanceof AdminTranslationRun) {
                return null;
            }

            $sections = $run->sections ?? [];
            $row = $sections[$section] ?? ['total' => 0, 'done' => 0, 'failed' => 0];
            $row['done'] += $done;
            $row['failed'] += $failed;
            $sections[$section] = $row;

            $run->done_items += $done;
            $run->failed_items += $failed;
            $run->sections = $sections;
            $run->progress = $run->total_items > 0 ? (int) min(99, floor(($run->done_items + $run->failed_items) * 100 / $run->total_items)) : 0;
            $run->save();

            return $run;
        });
    }

    public function addTokens(int $runId, int $prompt, int $completion): void
    {
        if ($prompt > 0 || $completion > 0) {
            $this->query()->whereKey($runId)->incrementEach(['prompt_tokens' => max(0, $prompt), 'completion_tokens' => max(0, $completion)]);
        }
    }
}
