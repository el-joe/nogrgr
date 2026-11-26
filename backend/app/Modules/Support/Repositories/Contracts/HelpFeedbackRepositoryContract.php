<?php

namespace App\Modules\Support\Repositories\Contracts;

use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<\App\Modules\Support\Models\HelpArticleFeedback>
 */
interface HelpFeedbackRepositoryContract extends RepositoryContract
{
    public function record(int $staffId, string $article, bool $helpful): void;

    public function voteOf(int $staffId, string $article): ?bool;
}
