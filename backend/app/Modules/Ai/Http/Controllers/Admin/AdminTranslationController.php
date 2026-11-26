<?php

namespace App\Modules\Ai\Http\Controllers\Admin;

use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Http\Requests\Admin\CatalogTranslationRequest;
use App\Modules\Ai\Http\Requests\Admin\StaticKeySuggestRequest;
use App\Modules\Ai\Http\Requests\Admin\StaticKeyTranslationRequest;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Ai\Services\AdminTranslation\AdminTranslationRunService;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Closure;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin AI translation (P10): ADM-091b catalogue run from Admin › Languages, ADM-092a
 * static keys modal / run / one-key suggestion from the translations editor, and the
 * live progress modal (status, retry, stop) of both.
 */
class AdminTranslationController extends Controller
{
    public function __construct(private readonly AdminTranslationRunService $service) {}

    public function catalog(CatalogTranslationRequest $request): JsonResponse
    {
        return $this->started(fn (): AdminTranslationRun => $this->service->queueCatalog($request->targetLocale(), $this->adminId()));
    }

    public function staticKeysModal(StaticKeyTranslationRequest $request, TranslationEditorService $editor): View
    {
        $locale = $request->targetLocale();

        return view('ai::admin.translation.static-keys-form', [
            'file' => $editor->findFile($request->fileId()),
            'locale' => $locale,
            'language' => $this->service->languageName($locale),
            'estimates' => [
                'file' => $this->service->estimateStaticKeys($locale, $request->fileId()),
                'all' => $this->service->estimateStaticKeys($locale, null),
            ],
        ]);
    }

    public function staticKeys(StaticKeyTranslationRequest $request): JsonResponse
    {
        return $this->started(fn (): AdminTranslationRun => $this->service->queueStaticKeys($request->targetLocale(), $request->scopedFile(), $this->adminId()));
    }

    public function suggest(StaticKeySuggestRequest $request): JsonResponse
    {
        try {
            $text = $this->service->suggestKey((string) $request->validated('file'), (string) $request->validated('key'), (string) $request->validated('locale'));
        } catch (AiRequestException $exception) {
            return JsonResponder::error($exception->getMessage(), $exception->type->httpStatus());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success('', ['text' => $text]);
    }

    public function show(AdminTranslationRun $run): JsonResponse
    {
        return JsonResponder::success('', $this->payload($run));
    }

    public function retry(AdminTranslationRun $run): JsonResponse
    {
        return $this->started(fn (): AdminTranslationRun => $this->service->retry($run), (string) __('ai::admin_translation.retried'));
    }

    public function stop(AdminTranslationRun $run): JsonResponse
    {
        try {
            $run = $this->service->stop($run);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('ai::admin_translation.stopped'), $this->payload($run));
    }

    /**
     * @param  Closure(): AdminTranslationRun  $action
     */
    private function started(Closure $action, ?string $message = null): JsonResponse
    {
        try {
            $run = $action();
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success($message ?? (string) __('ai::admin_translation.started'), $this->payload($run->refresh()));
    }

    /**
     * @return array{run: int, status: string, active: bool, status_url: string, html: string}
     */
    private function payload(AdminTranslationRun $run): array
    {
        $data = $this->service->present($run);

        return [
            'run' => $run->id,
            'status' => $run->status->value,
            'active' => $data['active'],
            'status_url' => route('admin.localization.ai-translation.runs.show', $run),
            'html' => view('ai::admin.translation.progress', ['run' => $data, 'canManage' => (bool) auth('admin')->user()?->can('manage', $run)])->render(),
        ];
    }

    private function adminId(): ?int
    {
        $id = auth('admin')->id();

        return is_numeric($id) ? (int) $id : null;
    }
}
