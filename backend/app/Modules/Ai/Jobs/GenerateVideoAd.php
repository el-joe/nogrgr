<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Services\VideoAds\VideoAdGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates one AI Advertising Studio ad (dispatched in the store's tenant context, so it
 * runs tenant-aware). Not retried automatically: every attempt spends OpenAI images; a
 * failed ad is retried by the vendor from the studio (counted again only when it succeeds
 * to call the provider).
 */
class GenerateVideoAd implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $adId) {}

    public function handle(VideoAdGenerator $generator): void
    {
        $generator->generate($this->adId);
    }
}
