<?php

namespace App\Modules\Ai\Models;

use App\Modules\Ai\Enums\VideoAdAspect;
use App\Modules\Ai\Enums\VideoAdOutput;
use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Enums\VideoAdPresenter;
use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Enums\VideoAdTone;
use App\Modules\Ai\Enums\VideoAdType;
use App\Modules\Ai\Enums\VideoAdVoice;
use App\Modules\Catalog\Models\StoreProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A video ad of the AI Advertising Studio (VEN-010c, tenant DB). Files live on the
 * store's (tenant-suffixed) public disk under `ai-ads/<id>/`.
 *
 * @property int $id
 * @property int|null $store_product_id
 * @property string $product_name
 * @property int|null $created_by
 * @property VideoAdType $video_type
 * @property VideoAdPresenter $presenter
 * @property VideoAdVoice $voice
 * @property VideoAdTone $tone
 * @property string $locale
 * @property VideoAdPlatform $platform
 * @property VideoAdAspect $aspect
 * @property int $duration
 * @property int|null $country_id
 * @property string|null $notes
 * @property VideoAdStatus $status
 * @property int $progress
 * @property string|null $headline
 * @property string|null $hook
 * @property list<array{caption: string, visual: string}>|null $scenes
 * @property string|null $voiceover
 * @property string|null $caption
 * @property list<string>|null $hashtags
 * @property VideoAdOutput|null $output
 * @property string|null $video_path
 * @property string|null $poster_path
 * @property string|null $audio_path
 * @property list<string>|null $frames
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class VideoAd extends Model
{
    public const DIRECTORY = 'ai-ads';

    protected $table = 'video_ads';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_product_id', 'product_name', 'created_by', 'video_type', 'presenter', 'voice', 'tone', 'locale',
        'platform', 'aspect', 'duration', 'country_id', 'notes', 'status', 'progress', 'headline', 'hook', 'scenes',
        'voiceover', 'caption', 'hashtags', 'output', 'video_path', 'poster_path', 'audio_path', 'frames', 'error',
        'started_at', 'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_product_id' => 'integer',
            'created_by' => 'integer',
            'video_type' => VideoAdType::class,
            'presenter' => VideoAdPresenter::class,
            'voice' => VideoAdVoice::class,
            'tone' => VideoAdTone::class,
            'platform' => VideoAdPlatform::class,
            'aspect' => VideoAdAspect::class,
            'duration' => 'integer',
            'country_id' => 'integer',
            'status' => VideoAdStatus::class,
            'progress' => 'integer',
            'scenes' => 'array',
            'hashtags' => 'array',
            'output' => VideoAdOutput::class,
            'frames' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StoreProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }

    public function directory(): string
    {
        return self::DIRECTORY.'/'.$this->id;
    }

    public function isReady(): bool
    {
        return $this->status === VideoAdStatus::Ready;
    }
}
