<?php

namespace App\Modules\Ai\Services\AdminTranslation;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Enums\AdminTranslationRunStatus;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Events\AdminTranslationRunUpdated;
use App\Modules\Ai\Exceptions\AdminTranslationException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Jobs\FinishAdminTranslationRun;
use App\Modules\Ai\Jobs\StartAdminTranslationRun;
use App\Modules\Ai\Jobs\TranslateAdminChunk;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Ai\Notifications\AdminTranslationRunNotification;
use App\Modules\Ai\Repositories\Contracts\AdminTranslationRunRepositoryContract;
use App\Modules\Ai\Services\Translation\GuardedTranslator;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use App\Modules\Ai\Services\Usage\AiUsageRecorder;
use App\Modules\Ai\Support\AdminTranslationRegistry;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin AI translation (P10, ADM-091b catalogue / ADM-092a static keys), custom provider
 * (FreeLLMAPI by default, decision #44 a):
 *  1. queue: one run per kind + locale (an active run is reused), then StartAdminTranslationRun;
 *  2. start: every section is scanned for items still missing texts in the locale, split
 *     into chunks of 20, one Bus batch of TranslateAdminChunk jobs;
 *  3. chunk: GuardedTranslator (placeholders / tags / URLs kept, one retry per text,
 *     usage logged as catalog_translation / static_key_translation); only empty fields
 *     are written, locked keys never;
 *  4. finish: completed, or failed with last_error; admins with the kind's manage
 *     permission are notified either way. Retry re-scans: filled items are no longer
 *     missing, so only failed / new ones are sent again.
 * Progress goes live over Reverb (AdminTranslationRunUpdated) after every chunk.
 */
class AdminTranslationRunService extends BaseService
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
        private readonly AdminTranslationRunRepositoryContract $runs,
        private readonly AdminTranslationRegistry $registry,
        private readonly TranslationClientFactory $clients,
        private readonly GuardedTranslator $translator,
        private readonly LanguageRepositoryContract $languages,
        private readonly AdminRepositoryContract $admins,
        private readonly TranslationEditorService $editor,
        private readonly TranslationKeyLockService $locks,
    ) {}

    public function queueCatalog(string $locale, ?int $adminId = null): AdminTranslationRun
    {
        if ($this->languages->findActiveByCode($locale) === null) {
            throw AdminTranslationException::unknownLanguage();
        }

        return $this->queue(AdminTranslationKind::Catalog, $locale, null, $adminId);
    }

    public function queueStaticKeys(string $locale, ?string $file, ?int $adminId = null): AdminTranslationRun
    {
        if ($locale === 'en' || ! array_key_exists($locale, $this->editor->locales())) {
            throw AdminTranslationException::unknownLanguage();
        }

        if ($this->registry->staticKeys($file)->keys($locale) === []) {
            throw AdminTranslationException::nothingMissing();
        }

        return $this->queue(AdminTranslationKind::StaticKeys, $locale, $file, $adminId);
    }

    /**
     * Figures of the ADM-092a modal: missing keys, their English words and locked keys
     * (skipped) of one file or of every file.
     *
     * @return array{keys: int, words: int, locked: int}
     */
    public function estimateStaticKeys(string $locale, ?string $file): array
    {
        $source = $this->registry->staticKeys($file);
        $keys = 0;
        $words = 0;

        if ($locale !== 'en') {
            foreach ($source->items($source->keys($locale), $locale) as $item) {
                $keys++;
                $words += str_word_count(strip_tags(implode(' ', $item->texts)));
            }
        }

        return ['keys' => $keys, 'words' => $words, 'locked' => $source->lockedCount($locale)];
    }

    /**
     * One-key "Suggest" of the translations editor: the English text of the key
     * translated into $locale (nothing saved).
     */
    public function suggestKey(string $fileId, string $key, string $locale): string
    {
        $file = $this->editor->findFile($fileId);

        if ($this->locks->isLocked($file->id, $key)) {
            throw AdminTranslationException::keyLocked();
        }

        $source = (string) ($this->editor->entry($file, $key)['values']['en'] ?? '');

        if (trim($source) === '' || $locale === 'en') {
            throw AdminTranslationException::noSource();
        }

        $item = TranslationItemData::make('key', ['value' => ['en', $source]], ['value']);
        $result = $this->translator->translate($this->client(), ['key' => $item], $locale, [], new AiUsageContext(AiFeature::StaticKeyTranslation, null, Str::limit('key:'.$file->id.'|'.$key, 180, '')));

        return $result['key']['value'] ?? throw AdminTranslationException::placeholders();
    }

    public function retry(AdminTranslationRun $run): AdminTranslationRun
    {
        if (! $run->status->canRetry()) {
            throw AdminTranslationException::cannotRetry();
        }

        $run = $this->update($run, [
            'status' => AdminTranslationRunStatus::Queued,
            'attempts' => $run->attempts + 1,
            'last_error' => null,
            'finished_at' => null,
        ]);
        $this->broadcast($run);
        StartAdminTranslationRun::dispatch($run->id);

        return $run->refresh();
    }

    public function stop(AdminTranslationRun $run): AdminTranslationRun
    {
        if (! $run->status->isActive()) {
            throw AdminTranslationException::notRunning();
        }

        $run = $this->update($run, ['status' => AdminTranslationRunStatus::Cancelled, 'finished_at' => now()]);
        $this->broadcast($run);

        return $run;
    }

    public function start(int $runId): void
    {
        $run = $this->runs->find($runId);

        if (! $run instanceof AdminTranslationRun || ! $run->status->isActive()) {
            return;
        }

        $provider = $this->clients->providerFor(TranslationPurpose::Custom);
        $chunks = [];
        $sections = [];

        foreach ($this->registry->sources($run) as $name => $source) {
            $keys = $source->keys($run->locale);
            $sections[$name] = ['total' => count($keys), 'done' => 0, 'failed' => 0];

            foreach (array_chunk($keys, self::CHUNK) as $chunk) {
                $chunks[] = new TranslateAdminChunk($run->id, $name, $chunk);
            }
        }

        $run = $this->update($run, [
            'status' => AdminTranslationRunStatus::Translating,
            'progress' => 0,
            'total_items' => array_sum(array_column($sections, 'total')),
            'done_items' => 0,
            'failed_items' => 0,
            'sections' => $sections,
            'last_error' => null,
            'provider' => $provider->value,
            'model' => AiProviderConfig::fromConfig($provider)->model,
            'started_at' => now(),
            'finished_at' => null,
        ]);
        $this->broadcast($run);

        if ($chunks === []) {
            FinishAdminTranslationRun::dispatch($run->id);

            return;
        }

        $runId = $run->id;
        Bus::batch($chunks)
            ->name('admin-translation:'.$runId)
            ->allowFailures()
            ->finally(static function () use ($runId): void {
                FinishAdminTranslationRun::dispatch($runId);
            })
            ->dispatch();
    }

    /**
     * Translate one chunk. Provider errors worth retrying are re-thrown unless
     * $lastAttempt (the job releases itself with backoff); otherwise the chunk's items
     * count as failed.
     *
     * @param  list<string>  $keys
     *
     * @throws AiRequestException
     */
    public function translateChunk(int $runId, string $section, array $keys, bool $lastAttempt = true): void
    {
        $run = $this->runs->find($runId);

        if (! $run instanceof AdminTranslationRun || $run->status !== AdminTranslationRunStatus::Translating) {
            return;
        }

        [$done, $failed] = $this->processChunk($run, $section, $keys, $lastAttempt);
        $run = $this->runs->advance($runId, $section, $done, $failed);

        if ($run !== null) {
            $this->broadcast($run);
        }
    }

    public function finish(int $runId): void
    {
        $run = $this->runs->find($runId);

        if (! $run instanceof AdminTranslationRun || $run->status !== AdminTranslationRunStatus::Translating) {
            return;
        }

        if ($run->failed_items > 0 || $run->last_error !== null) {
            $this->fail($runId, $run->last_error ?? (string) trans_choice('ai::admin_translation.errors.items_failed', $run->failed_items, ['count' => $run->failed_items]));

            return;
        }

        $run = $this->update($run, ['status' => AdminTranslationRunStatus::Completed, 'progress' => 100, 'finished_at' => now()]);
        $this->broadcast($run);
        $this->notifyAdmins($run, 'translation_completed');
    }

    public function fail(int $runId, string $error): void
    {
        $run = $this->runs->find($runId);

        if (! $run instanceof AdminTranslationRun || ! $run->status->isActive()) {
            return;
        }

        $run = $this->update($run, [
            'status' => AdminTranslationRunStatus::Failed,
            'last_error' => Str::limit($error, 480),
            'finished_at' => now(),
        ]);
        $this->broadcast($run);
        $this->notifyAdmins($run, 'translation_failed');
    }

    /**
     * Display data of a run (progress modal / page banner).
     *
     * @return array{id: int, kind: string, locale: string, language: string, status: AdminTranslationRunStatus, active: bool, progress: int, done: int, total: int, failed: int, error: string|null, sections: array<string, array{total: int, done: int, failed: int}>}
     */
    public function present(AdminTranslationRun $run): array
    {
        $sections = [];

        foreach ($run->kind->sections() as $name) {
            $row = $run->sections[$name] ?? ['total' => 0, 'done' => 0, 'failed' => 0];
            $sections[$name] = ['total' => (int) $row['total'], 'done' => (int) $row['done'], 'failed' => (int) $row['failed']];
        }

        return [
            'id' => $run->id,
            'kind' => $run->kind->value,
            'locale' => $run->locale,
            'language' => $this->languageName($run->locale),
            'status' => $run->status,
            'active' => $run->status->isActive(),
            'progress' => $run->status === AdminTranslationRunStatus::Completed ? 100 : $run->progress,
            'done' => $run->done_items,
            'total' => $run->total_items,
            'failed' => $run->failed_items,
            'error' => $run->last_error,
            'sections' => $sections,
        ];
    }

    public function languageName(string $code): string
    {
        $language = $this->languages->findByCode($code);

        return $language !== null ? (string) $language->name : strtoupper($code);
    }

    private function queue(AdminTranslationKind $kind, string $locale, ?string $file, ?int $adminId): AdminTranslationRun
    {
        $active = $this->runs->active($kind, $locale);

        if ($active !== null && $active->scopeFile() === $file) {
            return $active;
        }

        $run = $this->runs->create([
            'kind' => $kind,
            'locale' => $locale,
            'scope' => $kind === AdminTranslationKind::StaticKeys ? ['file' => $file] : null,
            'status' => AdminTranslationRunStatus::Queued,
            'sections' => array_fill_keys($kind->sections(), ['total' => 0, 'done' => 0, 'failed' => 0]),
            'admin_id' => $adminId,
        ]);
        $this->broadcast($run);
        StartAdminTranslationRun::dispatch($run->id);

        return $run->refresh();
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: int, 1: int} items done, items failed
     *
     * @throws AiRequestException
     */
    private function processChunk(AdminTranslationRun $run, string $section, array $keys, bool $lastAttempt): array
    {
        $source = $this->registry->source($run, $section);

        if ($source === null) {
            return [0, count($keys)];
        }

        if ($run->last_error !== null) {
            return [0, count($keys)];
        }

        $items = $source->items($keys, $run->locale);
        $done = count($keys) - count($items);

        if ($items === []) {
            return [$done, 0];
        }

        $before = AiUsageRecorder::usage($this->client());

        try {
            $results = $this->translator->translate($this->client(), $items, $run->locale, [], new AiUsageContext($run->kind->feature(), null, 'admin-run:'.$run->id));
        } catch (AiRequestException $exception) {
            if (! $lastAttempt && ! in_array($exception->type, self::FATAL, true)) {
                throw $exception;
            }

            if (in_array($exception->type, self::FATAL, true)) {
                $this->update($run, ['last_error' => Str::limit($exception->getMessage(), 480)]);
            }

            return [$done, count($items)];
        } finally {
            $after = AiUsageRecorder::usage($this->client());
            $this->runs->addTokens($run->id, $after['prompt'] - $before['prompt'], $after['completion'] - $before['completion']);
        }

        $translations = array_filter($results, static fn (?array $fields): bool => $fields !== null && $fields !== []);
        $failed = count($items) - count($translations);

        try {
            $source->write($run->locale, $translations);
        } catch (Throwable $exception) {
            report($exception);

            return [$done, count($items)];
        }

        return [$done + count($translations), $failed];
    }

    private function notifyAdmins(AdminTranslationRun $run, string $event): void
    {
        try {
            $admins = $this->admins->activeWithPermission($run->kind->managePermission());

            if ($admins->isEmpty()) {
                return;
            }

            $link = $run->kind === AdminTranslationKind::Catalog
                ? route('admin.localization.languages.index')
                : route('admin.localization.translations.index', array_filter(['locale' => $run->locale, 'file' => $run->scopeFile()]));

            Notification::send($admins, new AdminTranslationRunNotification($event, [
                'language' => $this->languageName($run->locale),
                'kind' => (string) __('ai::admin_translation.kinds.'.$run->kind->value),
                'count' => $run->failed_items,
            ], $link));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function broadcast(AdminTranslationRun $run): void
    {
        try {
            event(new AdminTranslationRunUpdated($run));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function client(): TranslationClient
    {
        return $this->client ??= $this->clients->for(TranslationPurpose::Custom);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(AdminTranslationRun $run, array $attributes): AdminTranslationRun
    {
        $run->forceFill($attributes)->save();

        return $run;
    }
}
