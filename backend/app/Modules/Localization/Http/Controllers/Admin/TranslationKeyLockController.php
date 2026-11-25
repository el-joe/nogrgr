<?php

namespace App\Modules\Localization\Http\Controllers\Admin;

use App\Modules\Localization\Http\Requests\Admin\TranslationEntryRequest;
use App\Modules\Localization\Http\Requests\Admin\TranslationQueryRequest;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Localization › Translation key locks (ADM-093, Figma 79:82911 "Locked" tab and
 * Lock column): lock / unlock a key so AI runs and vendors cannot change it.
 */
class TranslationKeyLockController extends Controller
{
    public function __construct(
        private readonly TranslationKeyLockService $locks,
        private readonly TranslationEditorService $editor,
    ) {}

    public function index(TranslationQueryRequest $request, TranslationController $translations): View
    {
        return $translations->page($request, 'locked');
    }

    /**
     * PATCH {locked: 1|0} from the Lock switch, or the menu items.
     */
    public function update(TranslationEntryRequest $request): JsonResponse
    {
        $file = $request->translationFile();
        $key = $request->translationKey();

        try {
            $this->editor->entry($file, $key);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 404);
        }

        if ($request->locked()) {
            $id = auth('admin')->id();
            $this->locks->lock($file->id, $key, is_numeric($id) ? (int) $id : null);

            return JsonResponder::success(__('localization::translations.locked', ['key' => $key]));
        }

        $this->locks->unlock($file->id, $key);

        return JsonResponder::success(__('localization::translations.unlocked', ['key' => $key]));
    }
}
