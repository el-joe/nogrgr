<?php

namespace App\Modules\Localization\Http\Controllers\Admin;

use App\Modules\Localization\DataTables\TranslationsDataTable;
use App\Modules\Localization\Http\Concerns\AnswersDomainErrors;
use App\Modules\Localization\Http\Requests\Admin\TranslationEntryRequest;
use App\Modules\Localization\Http\Requests\Admin\TranslationFileRequest;
use App\Modules\Localization\Http\Requests\Admin\TranslationQueryRequest;
use App\Modules\Localization\Http\Requests\Admin\TranslationValuesRequest;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use App\Shared\DataTables\DataTableFilters;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Localization › Translations (ADM-092, Figma 79:82911): storefront static text
 * base set + panel lang files per language, missing keys, edit / add key / create
 * file / revert (central overrides, deploy-safe) and JSON export. The locked tab is
 * ADM-093 (TranslationKeyLockController).
 */
class TranslationController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly TranslationEditorService $editor,
        private readonly TranslationFileRegistry $registry,
    ) {}

    public function index(TranslationQueryRequest $request): View
    {
        return $this->page($request, $request->editorTab());
    }

    public function data(TranslationQueryRequest $request, TranslationsDataTable $table): JsonResponse
    {
        $search = DataTableFilters::fromRequest($request, ['search'])['search'] ?? null;

        return $table->toJson($request->translationFile(), $request->editorLocale(), $request->editorTab(), is_string($search) ? $search : null);
    }

    public function show(TranslationEntryRequest $request): View
    {
        return $this->entryView('localization::admin.translations.entry', $request);
    }

    public function edit(TranslationEntryRequest $request): View
    {
        return $this->entryView('localization::admin.translations.form', $request);
    }

    public function update(TranslationValuesRequest $request): JsonResponse
    {
        $data = $request->valuesData();

        return $this->attempt(
            fn () => $this->editor->save($data, $this->adminId()),
            __('localization::translations.saved', ['key' => $data->key]),
        );
    }

    public function create(TranslationQueryRequest $request): View
    {
        return view('localization::admin.translations.form', [
            'file' => $request->translationFile(),
            'entry' => null,
            'locales' => $this->editor->locales(),
        ]);
    }

    public function store(TranslationValuesRequest $request): JsonResponse
    {
        $data = $request->valuesData();

        return $this->attempt(
            fn () => $this->editor->addKey($data, $this->adminId()),
            __('localization::translations.key_added', ['key' => $data->key]),
        );
    }

    public function createFile(): View
    {
        return view('localization::admin.translations.file-form', [
            'namespaces' => array_combine(array_keys($this->registry->moduleNamespaces()), array_keys($this->registry->moduleNamespaces())),
            'locales' => $this->editor->locales(),
        ]);
    }

    public function storeFile(TranslationFileRequest $request): JsonResponse
    {
        try {
            $file = $this->editor->createFile($request->fileData(), $this->adminId());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(
            __('localization::translations.file_created', ['file' => $file->label()]),
            redirect: route('admin.localization.translations.index', ['file' => $file->id]),
        );
    }

    public function revert(TranslationEntryRequest $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->editor->revert($request->translationFile()->id, $request->translationKey()),
            __('localization::translations.reverted', ['key' => $request->translationKey()]),
        );
    }

    public function export(TranslationQueryRequest $request): StreamedResponse
    {
        $file = $request->translationFile();
        $locale = $request->editorLocale();
        $lines = $this->editor->export($file, $locale);
        $name = str_replace('::', '-', $file->id).'.'.$locale.'.json';

        return response()->streamDownload(
            static function () use ($lines): void {
                echo json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            $name,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    /**
     * The editor page; the key locks page (ADM-093) is the same view on its "locked" tab.
     */
    public function page(TranslationQueryRequest $request, string $tab): View
    {
        $file = $request->translationFile();
        $locale = $request->editorLocale();

        return view('localization::admin.translations.index', [
            'file' => $file,
            'locale' => $locale,
            'tab' => $tab,
            'files' => $this->registry->options(),
            'locales' => $this->editor->locales(),
            'counts' => $this->editor->counts($file, $locale),
        ]);
    }

    private function entryView(string $view, TranslationEntryRequest $request): View
    {
        $file = $request->translationFile();

        try {
            $entry = $this->editor->entry($file, $request->translationKey());
        } catch (DomainException) {
            abort(404);
        }

        return view($view, [
            'file' => $file,
            'entry' => $entry,
            'locales' => $this->editor->locales(),
        ]);
    }

    private function adminId(): ?int
    {
        $id = auth('admin')->id();

        return is_numeric($id) ? (int) $id : null;
    }
}
