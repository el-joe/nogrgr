<?php

namespace App\Modules\Support\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\DataTables\SupportTicketsDataTable;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Http\Requests\OpenTicketRequest;
use App\Modules\Support\Http\Requests\TicketReplyRequest;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Services\SupportTicketService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor › Support (VEN-091 list + open thread, VEN-091a new ticket, VEN-091b thread;
 * Figma 88:111731 / 88:113398): the store's tickets (tenant scope), new ticket modal with
 * attachments, the live conversation (Reverb channel support.tickets.<id> + polling),
 * reply, close and reopen. support.view reads, support.manage writes.
 */
class TicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request): View
    {
        $ticket = $this->tickets->latest();

        if ($ticket !== null) {
            $this->tickets->openByRequester($ticket);
        }

        return $this->page($request, $ticket);
    }

    public function data(Request $request, SupportTicketsDataTable $table): JsonResponse
    {
        $selected = filter_var($request->query('selected'), FILTER_VALIDATE_INT);

        return $table->for('vendor', null, false, $selected === false ? null : $selected)
            ->toJsonFromRequest($request, SupportTicketsDataTable::FILTERS);
    }

    /**
     * New ticket form: modal fragment (remote modal) or a full page when opened by URL.
     */
    public function create(Request $request): View
    {
        $data = ['categories' => TicketCategory::options(), 'priorities' => TicketPriority::options()];

        return $request->ajax() ? view('support::vendor._form', $data) : view('support::vendor.create', $data);
    }

    public function store(OpenTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->open($request->toData(TicketActor::vendor($this->staff($request)), (int) tenant()?->getTenantKey()));
        $url = vendor_route('support.show', ['ticket' => $ticket->id]);

        return JsonResponder::created(__('support::tickets.opened', ['reference' => $ticket->displayReference()]), ['id' => $ticket->id, 'url' => $url], $url);
    }

    public function show(Request $request, int $ticket): View
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->staff($request))->authorize('view', $row);
        $this->tickets->openByRequester($row);

        return $this->page($request, $row);
    }

    public function messages(Request $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        $after = filter_var($request->query('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $messages = $this->tickets->messages($row, $after === false ? null : $after);

        if ($row->requester_unread) {
            $this->tickets->openByRequester($row);
        }

        return JsonResponder::success('', [
            'html' => $messages->map(fn (SupportTicketMessage $message): string => $this->messageHtml($message, $row))->implode(''),
            'last_id' => (int) ($messages->last()->id ?? $after ?: 0),
            'status' => ['label' => $row->status->requesterLabel(), 'tone' => $row->status->tone()],
        ]);
    }

    public function reply(TicketReplyRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->staff($request))->authorize('reply', $row);
        $message = $this->tickets->reply($row, $request->toData(TicketActor::vendor($this->staff($request))));

        return JsonResponder::created(__('support::tickets.sent'), [
            'html' => $this->messageHtml($message, $row),
            'last_id' => $message->id,
        ]);
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->staff($request))->authorize('reply', $row);
        $this->tickets->close($row, TicketActor::vendor($this->staff($request)));

        return JsonResponder::success(__('support::tickets.closed', ['reference' => $row->displayReference()]), null, vendor_route('support.show', ['ticket' => $row->id]));
    }

    public function reopen(Request $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->staff($request))->authorize('reply', $row);
        $this->tickets->reopen($row, TicketActor::vendor($this->staff($request)));

        return JsonResponder::success(__('support::tickets.reopened', ['reference' => $row->displayReference()]), null, vendor_route('support.show', ['ticket' => $row->id]));
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $file = $this->tickets->findAttachment($attachment) ?? throw new NotFoundHttpException;

        if (! Gate::forUser($this->staff($request))->allows('viewAttachment', [SupportTicket::class, $file])) {
            throw new NotFoundHttpException;
        }

        return $this->tickets->stream($file, $request->boolean('download'));
    }

    /**
     * Pusher-protocol channel auth for the store staff (private-support.tickets.<id>).
     */
    public function broadcastAuth(Request $request): Response
    {
        $request->setUserResolver(fn () => $this->staff($request));
        $result = Broadcast::auth($request);

        return $result instanceof Response ? $result : response()->json($result);
    }

    private function page(Request $request, ?SupportTicket $ticket): View
    {
        $staff = $this->staff($request);

        return view('support::vendor.index', [
            'ticket' => $ticket,
            'messages' => $ticket !== null ? $this->tickets->messages($ticket) : collect(),
            'canManage' => $staff->can('support.manage'),
            'realtime' => $ticket !== null ? $this->tickets->realtimeConfig($ticket, 'vendor') : null,
        ]);
    }

    private function messageHtml(SupportTicketMessage $message, SupportTicket $ticket): string
    {
        return view('support::tickets.thread._message', ['message' => $message, 'ticket' => $ticket, 'viewer' => 'vendor'])->render();
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
