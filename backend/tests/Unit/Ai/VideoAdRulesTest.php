<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\VideoAdAspect;
use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Services\VideoAds\VideoAdGenerator;
use App\Modules\Ai\Services\VideoAds\VideoAdRenderer;
use App\Modules\Billing\Enums\PlanLimit;

/*
| AI Advertising Studio rules: sizes per platform, scene count per length, the plan limit
| the feature is metered by, and the timed captions burned into the MP4.
*/

it('offers only the sizes a platform accepts, default first', function (): void {
    expect(VideoAdPlatform::TikTok->aspects())->toBe([VideoAdAspect::Vertical])
        ->and(VideoAdPlatform::InstagramFeed->aspects()[0])->toBe(VideoAdAspect::Portrait)
        ->and(VideoAdPlatform::X->supports(VideoAdAspect::Vertical))->toBeFalse()
        ->and(VideoAdAspect::Vertical->dimensions())->toBe([720, 1280])
        ->and(VideoAdAspect::Landscape->imageSize())->toBe('1536x1024');
});

it('meters video ads by the ai_ads plan limit and scales scenes with the length', function (): void {
    expect(AiFeature::VideoAd->planLimit())->toBe(PlanLimit::AiAds)
        ->and(AiFeature::meteredBy(PlanLimit::AiAds))->toBe([AiFeature::VideoAd])
        ->and(VideoAdGenerator::sceneCount(15))->toBe(4)
        ->and(VideoAdGenerator::sceneCount(30))->toBe(6);
});

it('times one caption per scene and picks an Arabic-capable font for RTL', function (): void {
    $ass = (new VideoAdRenderer)->subtitles(['ساعة {ذكية}', '', 'Shop now'], 5.0, 720, 1280, true);

    expect($ass)->toContain('PlayResY: 1280')
        ->toContain('Style: Caption,Noto Kufi Arabic,40,')
        ->toContain('Dialogue: 0,0:00:00.15,0:00:04.85,Caption,,0,0,0,,{\fad(200,200)}ساعة ذكية')
        ->toContain('Dialogue: 0,0:00:10.15,0:00:14.85,Caption,,0,0,0,,{\fad(200,200)}Shop now')
        ->and(substr_count($ass, 'Dialogue:'))->toBe(2);
});
