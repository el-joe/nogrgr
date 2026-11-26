<?php

namespace App\Modules\Ai\Services\StoreTranslation;

use App\Modules\Ai\Contracts\ReportsTokenUsage;
use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Events\StoreTranslationRunUpdated;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Jobs\FinishStoreTranslationRun;
use App\Modules\Ai\Jobs\TranslateStoreChunk;
use App\Modules\Ai\Notifications\StoreTranslationRunNotification;
use App\Modules\Ai\Repositories\Contracts\StoreTranslationItemRepositoryContract;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use App\Modules\Ai\Support\PlaceholderGuard;
use App\Modules\Ai\Support\StoreTranslationRegistry;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Localization\Services\LanguageService;
use App\Modules\Localization\Services\StoreTranslationService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Paid full-store AI translation run (decisions #44 / #45 / #97 b), started by
 * Localization\Events\StoreTranslationRunQueued:
 *  1. start: run → translating (counters reset, provider + model noted), every section
 *     of the StoreTranslationRegistry split into chunks, one Bus batch of chunk jobs;
 *  2. chunk: items already translated from the same source (hash) are skipped — so a
 *     retry resumes and redoes only failed / changed items —, the rest goes to the
 *     paid provider (Admin › Integrations › AI, OpenAI by default) with the store name
 *     kept as is; each translation must keep its placeholders / tags / URLs (one
 *     retry per text), hand-translated (locked) fields are never sent or written;
 *  3. finish: completed → the language is switched on for the store, or failed with
 *     last_error (the vendor retries). Progress goes live over Reverb after every
 *     chunk; the store owners are notified at the end.
 */
class StoreTranslationRunService extends BaseService
{
    public const CHUNK = 20;

    /**
     * Provider errors no retry can fix: the rest of the run stops calling the provider.
     *
     * @var list<AiErrorType>
     */
    private const FATAL = [AiErrorType::NotConfigured, AiErrorType::AuthFailed, AiErrorType::QuotaExhausted];

    private ?TranslationClient $client = null;

    public function __construct(
        private readonly StoreTranslationRunRepositoryContract $runs,
        private readonly StoreTranslationItemRepositoryContract $items,
        private readonly StoreTranslationRegistry $registry,
        private readonly TenantRepositoryContract $tenants,
        private readonly TranslationClientFactory $clients,
        private readonly LanguageService $languages,
        private readonly LanguageRepositoryContract $languageRows,
    ) {}

    public function start(int $runId): void
    {
        $run = $this->run($runId);

        if ($run === null || ! $run->status->isActive()) {
            return;
        }

        $tenant = $this->tenants->find($run->tenant_id);

        if (! $tenant instanceof Tenant) {
            $this->fail($runId, (string) __('ai::store_translation.errors.store_missing'));

            return;
        }

        $provider = $this->clients->providerFor(TranslationPurpose::Paid);
        $chunks = $tenant->run(fn (): array => $this->chunks($run->language_code));
        $total = array_sum(array_map(static fn (array $chunk): int => count($chunk['keys']), $chunks));

        $run = $this->update($run, [
            'status' => TranslationRunStatus::Translating,
            'progress' => 0,
            'total_items' => $total,
            'done_items' => 0,
            'failed_items' => 0,
            'last_error' => null,
            'provider' => $provider->value,
            'model' => AiProviderConfig::fromConfig($provider)->model,
            'started_at' => now(),
            'finished_at' => null,
        ]);
        $this->broadcast($run);

        if ($chunks === []) {
            FinishStoreTranslationRun::dispatch($runId);

            return;
        }

        $jobs = array_map(static fn (array $chunk): TranslateStoreChunk => new TranslateStoreChunk($runId, $chunk['section'], $chunk['keys']), $chunks);

        Bus::batch($jobs)
            ->name('store-translation:'.$runId)
            ->allowFailures()
            ->finally(static function () use ($runId): void {
                FinishStoreTranslationRun::dispatch($runId);
            })
            ->dispatch();
    }

    /**
     * Translate one chunk of a section. Provider errors worth retrying are re-thrown
     * unless $lastAttempt (the job releases itself with backoff); otherwise the chunk's
     * pending items are recorded as failed.
     *
     * @param  list<string>  $keys
     *
     * @throws AiRequestException
     */
    public function translateChunk(int $runId, StoreTranslationSection $section, array $keys, bool $lastAttempt = true): void
    {
        $run = $this->run($runId);

        if ($run === null || $run->status !== TranslationRunStatus::Translating) {
            return;
        }

        $tenant = $this->tenants->find($run->tenant_id);

        if (! $tenant instanceof Tenant) {
            return;
        }

        [$done, $failed] = $tenant->run(fn (): array => $this->processChunk($run, $tenant, $section, $keys, $lastAttempt));
        $this->advance($runId, $done, $failed);
    }

    public function finish(int $runId): void
    {
        $run = $this->run($runId);

        if ($run === null || $run->status !== TranslationRunStatus::Translating) {
            return;
        }

        $tenant = $this->tenants->find($run->tenant_id);

        if (! $tenant instanceof Tenant) {
            return;
        }

        if ($run->failed_items > 0 || $run->last_error !== null) {
            $this->fail($runId, $run->last_error ?? (string) trans_choice('ai::store_translation.errors.items_failed', $run->failed_items, ['count' => $run->failed_items]));

            return;
        }

        $this->languages->activateForTenant($tenant, $run->language_code);
        $tenant->run(fn () => app(StoreTranslationService::class)->forgetStaticTexts($run->language_code));

        $run = $this->update($run, [
            'status' => TranslationRunStatus::Completed,
            'progress' => 100,
            'finished_at' => now(),
        ]);
        $this->broadcast($run);
        $this->notifyOwners($tenant, $run, 'translation_completed');
    }

    /**
     * Mark the run failed (start problems, a crashed job or failed items).
     */
    public function fail(int $runId, string $error): void
    {
        $run = $this->run($runId);

        if ($run === null || ! $run->status->isActive()) {
            return;
        }

        $run = $this->update($run, [
            'status' => TranslationRunStatus::Failed,
            'last_error' => Str::limit($error, 480),
            'finished_at' => now(),
        ]);
        $this->broadcast($run);

        $tenant = $this->tenants->find($run->tenant_id);

        if ($tenant instanceof Tenant) {
            $this->notifyOwners($tenant, $run, 'translation_failed');
        }
    }

    /**
     * @return list<array{section: StoreTranslationSection, keys: list<string>}>
     */
    private function chunks(string $locale): array
    {
        $chunks = [];

        foreach ($this->registry->sources() as $source) {
            foreach (array_chunk($source->keys($locale), self::CHUNK) as $keys) {
                $chunks[] = ['section' => $source->section(), 'keys' => $keys];
            }
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: int, 1: int} items done, items failed
     *
     * @throws AiRequestException
     */
    private function processChunk(StoreTranslationRun $run, Tenant $tenant, StoreTranslationSection $section, array $keys, bool $lastAttempt): array
    {
        $locale = $run->language_code;

        if ($run->last_error !== null) {
            foreach ($keys as $key) {
                $this->items->markFailed($run->id, $section, $key, $locale, $run->last_error);
            }

            return [0, count($keys)];
        }

        $source = $this->registry->source($section);
        $doneHashes = $this->items->doneHashes($section, $locale, $keys);
        $items = $source->items($keys, $locale, array_fill_keys(array_keys($doneHashes), true));
        $done = 0;
        $failed = 0;
        $pending = [];

        foreach ($keys as $key) {
            $item = $items[$key] ?? null;

            if ($item === null || ($doneHashes[$key] ?? null) === $item->hash) {
                $done++;

                continue;
            }

            if ($item->texts === []) {
                $this->items->markDone($run->id, $section, $key, $locale, $item->hash);
                $done++;

                continue;
            }

            $pending[$key] = $item;
        }

        if ($pending === []) {
            return [$done, $failed];
        }

        $usageBefore = $this->usage();

        try {
            $results = $this->translateItems($pending, $locale, [(string) $tenant->name => '']);
        } catch (AiRequestException $exception) {
            if (! $lastAttempt && ! in_array($exception->type, self::FATAL, true)) {
                throw $exception;
            }

            if (in_array($exception->type, self::FATAL, true)) {
                $this->update($run, ['last_error' => $exception->getMessage()]);
            }

            foreach (array_keys($pending) as $key) {
                $this->items->markFailed($run->id, $section, $key, $locale, $exception->getMessage());
            }

            return [$done, $failed + count($pending)];
        } finally {
            $this->recordUsage($run->id, $usageBefore);
        }

        foreach ($pending as $key => $item) {
            $translations = $results[$key] ?? null;

            if ($translations === null) {
                $this->items->markFailed($run->id, $section, $key, $locale, (string) __('ai::store_translation.errors.placeholders'));
                $failed++;

                continue;
            }

            try {
                $source->write($key, $locale, $translations);
                $this->items->markDone($run->id, $section, $key, $locale, $item->hash);
                $done++;
            } catch (Throwable $exception) {
                report($exception);
                $this->items->markFailed($run->id, $section, $key, $locale, $exception->getMessage());
                $failed++;
            }
        }

        return [$done, $failed];
    }

    /**
     * Item key => translated fields, or null when a text lost its placeholders twice.
     *
     * @param  array<string, TranslationItemData>  $items
     * @param  array<string, string>  $glossary
     * @return array<string, array<string, string>|null>
     *
     * @throws AiRequestException
     */
    private function translateItems(array $items, string $locale, array $glossary): array
    {
        $results = [];

        $groups = [];

        foreach ($items as $key => $item) {
            foreach ($item->texts as $field => $text) {
                $groups[$item->from[$field]][] = [(string) $key, $field, $text];
            }
        }

        foreach ($groups as $from => $entries) {
            $texts = [];

            foreach ($entries as $index => [, , $text]) {
                $texts['t'.$index] = $text;
            }

            $translated = $this->client()->translate($texts, (string) $from, $locale, $glossary);

            foreach ($entries as $index => [$key, $field, $text]) {
                if (array_key_exists($key, $results) && $results[$key] === null) {
                    continue;
                }

                $checked = $this->checked($text, (string) ($translated['t'.$index] ?? ''), (string) $from, $locale, $glossary);
                $results[$key] = $checked === null ? null : [...($results[$key] ?? []), $field => $checked];
            }
        }

        return $results;
    }

    /**
     * The translation when it keeps the source's placeholders; otherwise one more try
     * for that text alone, then null.
     *
     * @param  array<string, string>  $glossary
     */
    private function checked(string $source, string $translation, string $from, string $locale, array $glossary): ?string
    {
        if (trim($translation) !== '' && PlaceholderGuard::preserved($source, $translation)) {
            return $translation;
        }

        $retry = (string) ($this->client()->translate(['text' => $source], $from, $locale, $glossary)['text'] ?? '');

        return trim($retry) !== '' && PlaceholderGuard::preserved($source, $retry) ? $retry : null;
    }

    private function advance(int $runId, int $done, int $failed): void
    {
        if ($done > 0 || $failed > 0) {
            $this->runs->query()->withoutGlobalScopes()->whereKey($runId)->incrementEach(['done_items' => $done, 'failed_items' => $failed]);
        }

        $run = $this->run($runId);

        if ($run === null) {
            return;
        }

        $progress = $run->total_items > 0 ? (int) min(99, floor(($run->done_items + $run->failed_items) * 100 / $run->total_items)) : 0;
        $run = $this->update($run, ['progress' => $progress]);
        $this->broadcast($run);
    }

    /**
     * @return array{prompt: int, completion: int}
     */
    private function usage(): array
    {
        $client = $this->client();

        return $client instanceof ReportsTokenUsage ? $client->tokenUsage() : ['prompt' => 0, 'completion' => 0];
    }

    /**
     * @param  array{prompt: int, completion: int}  $before
     */
    private function recordUsage(int $runId, array $before): void
    {
        $after = $this->usage();
        $prompt = $after['prompt'] - $before['prompt'];
        $completion = $after['completion'] - $before['completion'];

        if ($prompt > 0 || $completion > 0) {
            $this->runs->query()->withoutGlobalScopes()->whereKey($runId)->incrementEach(['prompt_tokens' => $prompt, 'completion_tokens' => $completion]);
        }
    }

    private function notifyOwners(Tenant $tenant, StoreTranslationRun $run, string $event): void
    {
        $language = $this->languageName($run->language_code);

        $tenant->run(function () use ($run, $event, $language): void {
            $owners = app(VendorStaffRepositoryContract::class)->activeOwners();

            if ($owners->isNotEmpty()) {
                Notification::send($owners, new StoreTranslationRunNotification($event, [
                    'language' => $language,
                    'count' => $run->failed_items,
                ], vendor_route('settings.languages.index')));
            }
        });
    }

    private function languageName(string $code): string
    {
        $language = $this->languageRows->findByCode($code);

        return $language !== null ? (string) $language->name : strtoupper($code);
    }

    private function broadcast(StoreTranslationRun $run): void
    {
        try {
            event(new StoreTranslationRunUpdated($run));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function client(): TranslationClient
    {
        return $this->client ??= $this->clients->for(TranslationPurpose::Paid);
    }

    private function run(int $runId): ?StoreTranslationRun
    {
        $run = $this->runs->query()->withoutGlobalScopes()->find($runId);

        return $run instanceof StoreTranslationRun ? $run : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(StoreTranslationRun $run, array $attributes): StoreTranslationRun
    {
        $run->forceFill($attributes)->save();

        return $run;
    }
}
