<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Http\Requests\Admin\FaqRequest;
use App\Modules\Content\Http\Requests\Admin\FaqSortRequest;
use App\Modules\Content\Models\Faq;
use App\Modules\Content\Repositories\Contracts\FaqRepositoryContract;
use App\Modules\Content\Services\FaqService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * FAQs (ADM-083 list with drag-sort and delete; ADM-083a create / edit).
 * Permissions: content.faqs.view / .manage.
 */
class FaqController extends Controller
{
    public function __construct(
        private readonly FaqService $service,
        private readonly FaqRepositoryContract $faqs,
    ) {}

    public function index(): View
    {
        return view('content::admin.faqs.index', ['faqs' => $this->faqs->ordered()]);
    }

    public function create(): View
    {
        return $this->form(new Faq(['group' => FaqGroup::General, 'is_active' => true]));
    }

    public function store(FaqRequest $request): JsonResponse
    {
        $faq = $this->service->create($request->toData());

        return JsonResponder::created(__('content::admin.common.created'), ['id' => $faq->id], route('admin.content.faqs.index'));
    }

    public function edit(Faq $faq): View
    {
        return $this->form($faq);
    }

    public function update(FaqRequest $request, Faq $faq): JsonResponse
    {
        $this->service->update($faq, $request->toData());

        return JsonResponder::success(__('content::admin.common.saved'), null, route('admin.content.faqs.index'));
    }

    public function sort(FaqSortRequest $request): JsonResponse
    {
        $this->service->reorder($request->ids());

        return JsonResponder::success(__('content::admin.faqs.sorted'));
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $this->service->delete($faq);

        return JsonResponder::success(__('content::admin.common.deleted'), null, route('admin.content.faqs.index'));
    }

    private function form(Faq $faq): View
    {
        return view('content::admin.faqs.form', ['faq' => $faq, 'groups' => FaqGroup::options()]);
    }
}
