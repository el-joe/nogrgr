<?php

namespace App\Modules\Ai\Services\Logo;

use App\Modules\Ai\DTOs\AiQuotaStatus;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\LogoColor;
use App\Modules\Ai\Enums\LogoStyle;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Storefront\Services\Appearance\StoreAppearanceService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * VEN-034a AI logo generator (OpenAI images only, decision #43): one generation = four
 * logo options saved as candidates on the store's public disk (`ai-logos/<batch>/`);
 * the vendor picks one and it replaces the store logo through the normal appearance
 * service (VEN-034b). Metered by the plan's monthly `ai_logos` (one per generation).
 */
class LogoGeneratorService
{
    public const OPTIONS = 4;

    public const DIRECTORY = 'ai-logos';

    public function __construct(
        private readonly OpenAiTools $tools,
        private readonly AiQuotaService $quota,
        private readonly StoreAppearanceService $appearance,
    ) {}

    public function quota(int $tenantId): AiQuotaStatus
    {
        return $this->quota->status(PlanLimit::AiLogos, $tenantId);
    }

    /**
     * @return array{batch: string, options: list<array{key: string, url: string}>}
     *
     * @throws AiQuotaExceededException|AiRequestException
     */
    public function generate(string $storeName, ?string $tagline, LogoStyle $style, LogoColor $color, int $tenantId): array
    {
        $prompt = 'A professional logo for an online store named "'.$storeName.'"'.($tagline ? ' (tagline: "'.$tagline.'")' : '').'. '
            .$style->prompt().', '.$color->prompt().'. Centered icon with the store name in clear readable lettering, '
            .'on a plain white background, vector style, no mockup, no photo, no extra text.';

        $images = $this->tools->run(
            new AiUsageContext(AiFeature::LogoGeneration, $tenantId, 'logo'),
            static fn (OpenAiCompatibleClient $client): array => $client->images($prompt, OpenAiTools::imageModel(), self::OPTIONS),
            timeout: 180,
        );

        $disk = Storage::disk('public');
        $batch = Str::lower(Str::random(16));
        $this->purgeOld();
        $options = [];

        foreach (array_slice($images, 0, self::OPTIONS) as $index => $binary) {
            $path = self::DIRECTORY.'/'.$batch.'/option-'.($index + 1).'.png';
            $disk->put($path, $binary);
            $options[] = ['key' => $batch.'/option-'.($index + 1), 'url' => $disk->url($path)];
        }

        return ['batch' => $batch, 'options' => $options];
    }

    /**
     * Make a generated option the store logo; the batch's candidates are removed.
     */
    public function apply(string $key): string
    {
        if (preg_match('~^([a-z0-9]{16})/option-([1-9])$~', $key, $match) !== 1) {
            throw new DomainException((string) __('ai::tools.logo.expired'));
        }

        $disk = Storage::disk('public');
        $path = self::DIRECTORY.'/'.$key.'.png';

        if (! $disk->exists($path)) {
            throw new DomainException((string) __('ai::tools.logo.expired'));
        }

        $this->appearance->replaceLogo(new UploadedFile($disk->path($path), 'ai-logo.png', 'image/png', null, true));
        $disk->deleteDirectory(self::DIRECTORY.'/'.$match[1]);

        return (string) $this->appearance->logoUrl();
    }

    /**
     * Candidates older than a day are dropped (never chosen).
     */
    private function purgeOld(): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->directories(self::DIRECTORY) as $directory) {
            $files = $disk->files($directory);

            if ($files === [] || $disk->lastModified($files[0]) < now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
