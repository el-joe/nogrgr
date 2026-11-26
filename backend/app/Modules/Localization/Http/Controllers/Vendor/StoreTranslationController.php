<?php

namespace App\Modules\Localization\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\DataTables\StoreContentTranslationsDataTable;
use App\Modules\Localization\DataTables\StoreStaticTextsDataTable;
use App\Modules\Localization\Enums\StoreContentType;
use App\Modules\Localization\Http\Concerns\AnswersDomainErrors;
use App\Modules\Localization\Http\Requests\Vendor\StoreTranslationRequest;
use App\Modules\Localization\Services\StoreTranslationService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Store translations (VEN-075, Figma Vendor Panel (new screens)
 * 90:114518): storefront static texts per language (tabs all / missing / locked, edit,
 * reset, "Translate" suggestion, "Translate missing with AI" queued) and the store's own
 * content fields per language (edit locks the field from the paid AI run, unlock).
 * Permissions: settings.translations.view (pages) / settings.translations.manage (writes).
 */
class StoreTranslationController extends Controller
{
    use AnswersDomainErrors;

    private const TABS = ['all', 'missing', 'locked', 'edited'];

    public function __construct(private readonly StoreTranslationService $translations) {}

    public function index(Request $request): View
    {
        $locale = $this->locale($request);
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'all';

        return view('localization::vendor.translations.index', [
            'section' => 'static',
            'locale' => $locale,
            'locales' => $this->translations->locales(),
            'tab' => $tab,
            'counts' => $this->translations->staticCounts($locale),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function data(Request $request, StoreStaticTextsDataTable $table): JsonResponse
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'all';

        return $table->toJson($this->locale($request), $tab, $this->search($request), $this->canManage($request));
    }

    public function content(Request $request): View
    {
        $type = StoreContentType::tryFrom((string) $request->query('type')) ?? StoreContentType::Category;
        $field = in_array($request->query('field'), $type->fields(), true) ? (string) $request->query('field') : $type->fields()[0];

        return view('localization::vendor.translations.content', [
            'section' => 'content',
            'locale' => $this->locale($request),
            'locales' => $this->translations->locales(),
            'type' => $type,
            'types' => StoreContentType::options(),
            'field' => $field,
            'fields' => collect($type->fields())->mapWithKeys(fn (string $name): array => [$name => __('localization::vendor_translations.fields_names.'.$name)])->all(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function contentData(Request $request, StoreContentTranslationsDataTable $table): JsonResponse
    {
        $type = StoreContentType::tryFrom((string) $request->query('type')) ?? StoreContentType::Category;
        $field = in_array($request->query('field'), $type->fields(), true) ? (string) $request->query('field') : $type->fields()[0];

        return $table->toJson($type, $field, $this->locale($request), $this->search($request), $this->canManage($request));
    }

    public function updateStatic(StoreTranslationRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->translations->updateStatic($request->key(), $request->locale(), $request->value()), __('localization::vendor_translations.saved'));
    }

    public function resetStatic(StoreTranslationRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->translations->resetStatic($request->key(), $request->locale()), __('localization::vendor_translations.reset_done'));
    }

    public function suggestStatic(StoreTranslationRequest $request): JsonResponse
    {
        return $this->suggestion(fn (): string => $this->translations->suggestStatic($request->key(), $request->locale()));
    }

    public function translateMissing(StoreTranslationRequest $request): JsonResponse
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);
        $this->translations->queueMissing($tenant, $request->locale());

        return JsonResponder::success(__('localization::vendor_translations.missing_queued', ['language' => $this->translations->locales()[$request->locale()] ?? $request->locale()]));
    }

    public function updateContent(StoreTranslationRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->translations->updateContent($request->type(), $request->recordId(), $request->field(), $request->locale(), $request->value()), __('localization::vendor_translations.saved'));
    }

    public function unlockContent(StoreTranslationRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->translations->unlockContent($request->type(), $request->recordId(), $request->field(), $request->locale()), __('localization::vendor_translations.unlocked'));
    }

    public function suggestContent(StoreTranslationRequest $request): JsonResponse
    {
        return $this->suggestion(fn (): string => $this->translations->suggestContent($request->type(), $request->recordId(), $request->field(), $request->locale()));
    }

    /**
     * @param  callable(): string  $suggest
     */
    private function suggestion(callable $suggest): JsonResponse
    {
        try {
            $text = $suggest();
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success('', ['text' => $text]);
    }

    private function locale(Request $request): string
    {
        $locale = (string) $request->query('locale', '');

        return $this->translations->isStoreLocale($locale) ? $locale : $this->translations->defaultLocale();
    }

    private function search(Request $request): ?string
    {
        $search = $request->input('search.value', $request->query('search'));

        return is_string($search) ? $search : null;
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('settings.translations.manage');
    }
}
