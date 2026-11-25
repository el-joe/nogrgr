<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\DataTables\ThreadedRequestsDataTable;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Http\Requests\Admin\Requests\PaymentRequestStoreRequest;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestNotesRequest;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestReplyRequest;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestStatusRequest;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Services\Requests\RequestPaymentService;
use App\Modules\Catalog\Services\Requests\RequestsCenterService;
use App\Modules\Catalog\Services\Requests\RequestThreadService;
use App\Modules\Catalog\Services\Requests\ThreadedRequestService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Requests center tabs with a conversation (product ADM-018c/d, manufacturing 018e/f,
 * brand 018g/h): list + data, detail with the shared thread, polling endpoint for new
 * messages (fallback of the Reverb push), reply, status, notes and payment requests.
 * Subclasses only name their kind. Permissions: requests.<kind>.view / .manage.
 */
abstract class ThreadedRequestController extends Controller
{
    public function __construct(
        protected readonly ThreadedRequestService $requests,
        protected readonly RequestThreadService $threads,
        protected readonly RequestPaymentService $payments,
        protected readonly RequestsCenterService $center,
    ) {}

    abstract protected function kind(): RequestKind;

    /**
     * @return ThreadedRequestsDataTable<covariant Model&ThreadedRequest>
     */
    abstract protected function table(): ThreadedRequestsDataTable;

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return view('catalog::admin.requests.threads.index', [
            'kind' => $this->kind(),
            'tabs' => $this->center->tabs($admin),
            'statuses' => $this->requests->statusOptions($this->kind()),
            'canManage' => $admin->can($this->kind()->permission('manage')),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return $this->table()->toJsonFromRequest($request, ThreadedRequestsDataTable::FILTERS);
    }

    public function show(Request $request, int $id): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $thread = $this->find($id);
        $this->threads->openByAdmin($thread);
        $thread->loadMissing('tenant');

        return view('catalog::admin.requests.threads.show', [
            'kind' => $this->kind(),
            'request' => $thread,
            'messages' => $this->threads->messages($thread),
            'attachments' => $this->threads->attachments($thread),
            'payments' => $this->kind()->hasPayments() ? $this->payments->forRequest($thread) : collect(),
            'statuses' => $this->requests->statusOptions($this->kind()),
            'canManage' => $admin->can($this->kind()->permission('manage')),
        ]);
    }

    /**
     * New messages after ?after=<id> (rendered rows) + current status; polled by the page
     * when Reverb is unavailable and after each live push.
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $thread = $this->find($id);
        $after = filter_var($request->query('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $messages = $this->threads->messages($thread, $after === false ? null : $after);

        if ($messages->contains(static fn (RequestMessage $message): bool => $message->sender === MessageSender::Vendor && $message->read_at === null)) {
            $this->threads->openByAdmin($thread);
        }

        return JsonResponder::success('', [
            'html' => $messages->map(fn (RequestMessage $message): string => view('catalog::admin.requests.thread._message', ['message' => $message, 'request' => $thread])->render())->implode(''),
            'last_id' => (int) ($messages->last()->id ?? $after ?: 0),
            'status' => ['label' => $thread->statusLabel(), 'tone' => $thread->statusTone()],
        ]);
    }

    public function reply(RequestReplyRequest $request, int $id): JsonResponse
    {
        $thread = $this->find($id);
        $message = $this->threads->post($thread, $request->toData());

        return JsonResponder::created(__('catalog::requests.thread.sent'), [
            'html' => view('catalog::admin.requests.thread._message', ['message' => $message, 'request' => $thread])->render(),
            'last_id' => $message->id,
        ]);
    }

    public function status(RequestStatusRequest $request, int $id): JsonResponse
    {
        $thread = $this->find($id);
        $this->requests->changeStatus($thread, $request->status());

        return JsonResponder::success(__('catalog::requests.status_changed', ['reference' => $thread->reference(), 'status' => $thread->statusLabel()]));
    }

    public function notes(RequestNotesRequest $request, int $id): JsonResponse
    {
        $this->guardPayments();
        $thread = $this->find($id);
        $this->requests->saveNotes($thread, $request->notes());

        return JsonResponder::success(__('catalog::requests.notes.saved'));
    }

    public function storePayment(PaymentRequestStoreRequest $request, int $id): JsonResponse
    {
        $this->guardPayments();
        $thread = $this->find($id);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $payment = $this->payments->create($thread, $request->toData(), $admin);

        return JsonResponder::created(__('catalog::requests.payments.created', ['label' => $payment->label, 'amount' => $payment->amount->format()]), ['id' => $payment->id]);
    }

    public function cancelPayment(int $id, int $payment): JsonResponse
    {
        $this->guardPayments();
        $thread = $this->find($id);
        $row = $this->payments->find($thread, $payment) ?? throw new NotFoundHttpException;
        $this->payments->cancel($thread, $row);

        return JsonResponder::success(__('catalog::requests.payments.cancelled', ['label' => $row->label]));
    }

    protected function find(int $id): Model&ThreadedRequest
    {
        return $this->requests->findOrFail($this->kind(), $id);
    }

    private function guardPayments(): void
    {
        if (! $this->kind()->hasPayments()) {
            throw new NotFoundHttpException;
        }
    }
}
