<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Modules\Content\DataTables\NewsletterSubscribersDataTable;
use App\Modules\Content\Enums\SubscriberStatus;
use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Content\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Modules\Content\Services\NewsletterService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Newsletter subscribers (ADM-084): list with status counts, CSV export, delete.
 * Permissions: content.newsletter.view / .export / .manage.
 */
class NewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterService $service,
        private readonly NewsletterSubscriberRepositoryContract $subscribers,
    ) {}

    public function index(): View
    {
        return view('content::admin.newsletter.index', ['statuses' => SubscriberStatus::options(), 'counts' => $this->subscribers->statusCounts()]);
    }

    public function data(Request $request, NewsletterSubscribersDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['status', 'search']);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->service->export(SubscriberStatus::tryFrom((string) $request->query('status', '')));
    }

    public function destroy(NewsletterSubscriber $subscriber): JsonResponse
    {
        $this->service->delete($subscriber);

        return JsonResponder::success(__('content::admin.common.deleted'));
    }
}
