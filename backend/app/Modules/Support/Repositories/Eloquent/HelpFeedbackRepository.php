<?php

namespace App\Modules\Support\Repositories\Eloquent;

use App\Modules\Support\Models\HelpArticleFeedback;
use App\Modules\Support\Repositories\Contracts\HelpFeedbackRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * Help center votes of the current store's staff (tenant scope + tenant_id filled on create).
 *
 * @extends BaseRepository<HelpArticleFeedback>
 */
class HelpFeedbackRepository extends BaseRepository implements HelpFeedbackRepositoryContract
{
    protected function model(): string
    {
        return HelpArticleFeedback::class;
    }

    public function record(int $staffId, string $article, bool $helpful): void
    {
        HelpArticleFeedback::query()->updateOrCreate(
            ['tenant_id' => tenant()?->getTenantKey(), 'staff_id' => $staffId, 'article' => $article],
            ['helpful' => $helpful],
        );
    }

    public function voteOf(int $staffId, string $article): ?bool
    {
        $row = HelpArticleFeedback::query()->where('staff_id', $staffId)->where('article', $article)->first();

        return $row?->helpful;
    }
}
