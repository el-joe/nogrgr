<?php

namespace App\Modules\Localization\Http\Controllers\Admin;

use App\Modules\Localization\DataTables\LanguagesDataTable;
use App\Modules\Localization\DTOs\LanguageData;
use App\Modules\Localization\Enums\ActiveState;
use App\Modules\Localization\Http\Concerns\AnswersDomainErrors;
use App\Modules\Localization\Http\Requests\Admin\LanguageRequest;
use App\Modules\Localization\Http\Requests\Admin\ToggleActiveRequest;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Localization\Services\LanguageService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Localization › Languages (ADM-091 / 091a): ISO list, create / edit,
 * active switch and delete. Arabic and English are locked (always active, free).
 */
class LanguageController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly LanguageService $languages,
        private readonly CountryService $countries,
    ) {}

    public function index(Request $request): View
    {
        return view('localization::admin.languages.index', [
            'tab' => $request->query('tab') === 'all' ? 'all' : ActiveState::Active->value,
            'counts' => $this->languages->counts(),
        ]);
    }

    public function data(Request $request, LanguagesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, LanguagesDataTable::FILTERS);
    }

    public function create(): View
    {
        return view('localization::admin.languages.form', [
            'language' => null,
            'countries' => $this->countries->options(),
        ]);
    }

    public function store(LanguageRequest $request): JsonResponse
    {
        $language = $this->languages->create(LanguageData::fromArray($request->payload()));

        return JsonResponder::created(__('localization::admin.languages.created', ['name' => $language->name]), redirect: route('admin.localization.languages.index', ['tab' => 'all']));
    }

    public function edit(Language $language): View
    {
        return view('localization::admin.languages.form', [
            'language' => $language,
            'countries' => $this->countries->options(),
        ]);
    }

    public function update(LanguageRequest $request, Language $language): JsonResponse
    {
        return $this->attempt(
            fn () => $this->languages->update($language, LanguageData::fromArray($request->payload())),
            __('localization::admin.languages.updated', ['name' => $language->name]),
            route('admin.localization.languages.index'),
        );
    }

    public function toggle(ToggleActiveRequest $request, Language $language): JsonResponse
    {
        return $this->attempt(
            fn () => $this->languages->setActive($language, $request->active()),
            __($request->active() ? 'localization::admin.languages.activated' : 'localization::admin.languages.deactivated', ['name' => $language->name]),
        );
    }

    public function destroy(Language $language): JsonResponse
    {
        return $this->attempt(
            fn () => $this->languages->delete($language),
            __('localization::admin.languages.deleted', ['name' => $language->name]),
        );
    }
}
