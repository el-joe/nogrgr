<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\DataTables\Vendor\VendorRequestsDataTable;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Http\Requests\Vendor\ListFilterRequest;
use App\Modules\Catalog\Http\Requests\Vendor\Requests\VendorRequestListRequest;
use App\Modules\Catalog\Http\Requests\Vendor\Requests\VendorRequestReplyRequest;
use App\Modules\Catalog\Http\Requests\Vendor\Requests\VendorRequestStoreRequest;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Services\Requests\RequestCheckoutService;
use App\Modules\Catalog\Services\Requests\RequestPaymentService;
use App\Modules\Catalog\Services\Requests\RequestThreadService;
use App\Modules\Catalog\Services\Requests\ThreadedRequestService;
use App\Modules\Catalog\Services\Requests\VendorRequestService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Services\CountryService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Requests (VEN-050 … 050i): hub redirect, list + stats per kind, new request
 * (product / brand modal, manufacturing page), detail with the shared live conversation,
 * reply, cancel (manufacturing), delete (pending brand request), CSV export and the
 * Reverb channel auth of "requests.<kind>.<id>". The kind is a route default.
 */
class VendorRequestController extends Controller
{
    public function __construct(
        private readonly VendorRequestService $requests,
        private readonly RequestThreadService $threads,
        private readonly RequestPaymentService $payments,
    ) {}

    public function hub(Request $request): RedirectResponse
    {
        $url = $this->requests->firstRoute($this->staff($request));
        abort_if($url === null, 403);

        return redirect()->to($url);
    }

    public function index(Request $request): View
    {
        $kind = $this->kind($request);
        $staff = $this->staff($request);

        return view('catalog::vendor.requests.index', [
            'kind' => $kind,
            'tabs' => $this->requests->tabs($staff),
            'stats' => $this->requests->stats($kind),
            'statuses' => $this->statusOptions($kind),
            'canManage' => $staff->can($kind->permission('manage')),
        ]);
    }

    public function data(VendorRequestListRequest $request): JsonResponse
    {
        return VendorRequestsDataTable::for($this->kind($request))->toJson($request->filters());
    }

    public function export(VendorRequestListRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        return response()->streamDownload(
            fn () => $this->requests->exportManufacturing(fopen('php://output', 'wb'), $filters),
            'manufacturing-requests-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * New request form: a modal fragment for product / brand requests (full page when
     * opened by URL), the "New manufacturing request" page for manufacturing.
     */
    public function create(Request $request, CountryService $countries, CategoryRepositoryContract $categories): View
    {
        $kind = $this->kind($request);
        $data = [
            'kind' => $kind,
            'countries' => $kind === RequestKind::Manufacturing ? [] : $countries->options(),
            'categories' => $kind === RequestKind::Brand ? $categories->activeTopLevel()->mapWithKeys(static fn ($category): array => [$category->id => (string) $category->name])->all() : [],
        ];

        if ($kind === RequestKind::Manufacturing) {
            return view('catalog::vendor.requests.create-manufacturing', $data);
        }

        return $request->ajax()
            ? view('catalog::vendor.requests._form', $data)
            : view('catalog::vendor.requests.create', $data);
    }

    public function store(VendorRequestStoreRequest $request): JsonResponse
    {
        $created = $this->requests->submit($request->toData());
        $url = vendor_route('requests.'.$created->kind()->value.'.show', ['id' => $created->getKey()]);

        return JsonResponder::created(__('catalog::vendor_requests.submitted', ['reference' => $created->reference()]), ['id' => $created->getKey(), 'url' => $url], $url);
    }

    public function show(Request $request, int $id, RequestCheckoutService $checkout): View
    {
        $kind = $this->kind($request);
        $thread = $this->requests->findOwned($kind, $id);
        $this->requests->open($thread);

        return view('catalog::vendor.requests.show', [
            'kind' => $kind,
            'request' => $thread,
            'messages' => $this->threads->messages($thread),
            'attachments' => $this->threads->attachments($thread),
            'payments' => $kind->hasPayments() ? $this->payments->forRequest($thread) : collect(),
            'gateways' => $kind->hasPayments() ? $checkout->gateways() : collect(),
            'canManage' => $this->staff($request)->can($kind->permission('manage')),
            'realtime' => $this->threads->realtimeConfig($thread, 'vendor'),
        ]);
    }

    /**
     * New messages after ?after=<id> + current status (polling fallback / after a push).
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $thread = $this->requests->findOwned($this->kind($request), $id);
        $after = filter_var($request->query('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $messages = $this->threads->messages($thread, $after === false ? null : $after);

        if ($messages->contains(static fn (RequestMessage $message): bool => $message->sender === MessageSender::Admin && $message->read_at === null)) {
            $this->requests->open($thread);
        }

        return JsonResponder::success('', [
            'html' => $messages->map(fn (RequestMessage $message): string => $this->messageHtml($message, $thread))->implode(''),
            'last_id' => (int) ($messages->last()->id ?? $after ?: 0),
            'status' => ['label' => $thread->statusLabel(), 'tone' => $thread->statusTone()],
        ]);
    }

    public function reply(VendorRequestReplyRequest $request, int $id): JsonResponse
    {
        $thread = $this->requests->findOwned($this->kind($request), $id);
        $message = $this->threads->post($thread, $request->toData());

        return JsonResponder::created(__('catalog::requests.thread.sent'), [
            'html' => $this->messageHtml($message, $thread),
            'last_id' => $message->id,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $thread = $this->requests->findOwned(RequestKind::Manufacturing, $id);
        abort_unless($thread instanceof ManufacturingRequest, 404);
        $this->requests->cancel($thread);

        return JsonResponder::success(__('catalog::vendor_requests.cancelled', ['reference' => $thread->reference()]));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $thread = $this->requests->findOwned(RequestKind::Brand, $id);
        abort_unless($thread instanceof BrandRequest, 404);
        $reference = $thread->reference();
        $this->requests->delete($thread);

        return JsonResponder::success(__('catalog::vendor_requests.deleted', ['reference' => $reference]), [], vendor_route('requests.brand.index'));
    }

    /**
     * Select2 source of the "Link to existing product" picker (store's central products).
     */
    public function products(ListFilterRequest $request, StoreCatalogService $catalog, StoreCatalogReader $reader): JsonResponse
    {
        $page = $reader->withRelations($catalog->listing($request->filters()))->orderBy('sort_order')->orderByDesc('id')->paginate(20, ['*'], 'page', (int) $request->validated('page', 1));

        return response()->json([
            'results' => array_map(static fn (StoreProductView $view): array => [
                'id' => $view->id(),
                'text' => $view->name.($view->sku ? ' · '.$view->sku : ''),
            ], $reader->views($page->getCollection())),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    /**
     * Pusher-protocol channel auth for the store staff (private-requests.<kind>.<id>).
     */
    public function broadcastAuth(Request $request): Response
    {
        $request->setUserResolver(fn () => $this->staff($request));
        $result = Broadcast::auth($request);

        return $result instanceof Response ? $result : response()->json($result);
    }

    private function messageHtml(RequestMessage $message, ThreadedRequest $thread): string
    {
        return view('catalog::admin.requests.thread._message', ['message' => $message, 'request' => $thread, 'viewer' => 'vendor'])->render();
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(RequestKind $kind): array
    {
        return ['open' => (string) __('catalog::vendor_requests.filters.open'), ...app(ThreadedRequestService::class)->statusOptions($kind)];
    }

    private function kind(Request $request): RequestKind
    {
        return RequestKind::fromVendorRouteName($request->route()?->getName()) ?? abort(404);
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
