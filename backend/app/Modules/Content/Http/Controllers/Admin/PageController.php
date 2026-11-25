<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Modules\Content\DataTables\PagesDataTable;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Http\Requests\Admin\PageRequest;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Services\PageService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Static pages (ADM-082 list: activate, archive, delete; ADM-082a create / edit with
 * target and compliance flag). Permissions: content.pages.view / .manage.
 */
class PageController extends Controller
{
    public function __construct(private readonly PageService $service) {}

    public function index(): View
    {
        return view('content::admin.pages.index', [
            'statuses' => PageStatus::options(),
            'targets' => PageTarget::options(),
        ]);
    }

    public function data(Request $request, PagesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['status', 'target', 'search']);
    }

    public function create(): View
    {
        return $this->form(new Page(['status' => PageStatus::Draft, 'target' => PageTarget::Central]));
    }

    public function store(PageRequest $request): JsonResponse
    {
        $page = $this->service->create($request->toData());

        return JsonResponder::created(__('content::admin.common.created'), ['id' => $page->id], route('admin.content.pages.index'));
    }

    public function edit(Page $page): View
    {
        return $this->form($page);
    }

    public function update(PageRequest $request, Page $page): JsonResponse
    {
        $this->service->update($page, $request->toData());

        return JsonResponder::success(__('content::admin.common.saved'), null, route('admin.content.pages.index'));
    }

    public function activate(Page $page): JsonResponse
    {
        $this->service->setStatus($page, PageStatus::Active);

        return JsonResponder::success(__('content::admin.pages.status_changed'));
    }

    public function archive(Page $page): JsonResponse
    {
        $this->service->setStatus($page, PageStatus::Archived);

        return JsonResponder::success(__('content::admin.pages.status_changed'));
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->service->delete($page);

        return JsonResponder::success(__('content::admin.common.deleted'));
    }

    private function form(Page $page): View
    {
        return view('content::admin.pages.form', [
            'page' => $page,
            'statuses' => PageStatus::options(),
            'targets' => PageTarget::options(),
        ]);
    }
}
