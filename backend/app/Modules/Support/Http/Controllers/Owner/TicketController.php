<?php

namespace App\Modules\Support\Http\Controllers\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Support\DataTables\SupportTicketsDataTable;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Http\Requests\OwnerOpenTicketRequest;
use App\Modules\Support\Http\Requests\TicketReplyRequest;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Services\SupportTicketService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Owner portal › Help & support tickets (OWN-009): the owner's tickets and their stores'
 * (list on the Help & support page), new ticket for a picked store (or the account), the
 * conversation (polling), reply, close and reopen.
 */
class TicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function data(Request $request, SupportTicketsDataTable $table): JsonResponse
    {
        return $table->for('owner', $this->tickets->ownerScope($this->owner($request)))
            ->toJsonFromRequest($request, SupportTicketsDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        $data = [
            'categories' => TicketCategory::options(),
            'priorities' => TicketPriority::options(),
            'stores' => $this->owner($request)->tenants()->orderBy('name')->pluck('name', 'id')->all(),
        ];

        return $request->ajax() ? view('support::owner._form', $data) : view('support::owner.create', $data);
    }

    public function store(OwnerOpenTicketRequest $request): JsonResponse
    {
        $owner = $this->owner($request);
        $ticket = $this->tickets->open($request->toData(TicketActor::owner($owner), $request->tenantId(), (int) $owner->id));
        $url = route('owner.support.tickets.show', $ticket->id);

        return JsonResponder::created(__('support::tickets.opened', ['reference' => $ticket->displayReference()]), ['id' => $ticket->id, 'url' => $url], $url);
    }

    public function show(Request $request, int $ticket): View
    {
        $row = $this->find($request, $ticket);
        $this->tickets->openByRequester($row);

        return view('support::owner.show', [
            'ticket' => $row,
            'messages' => $this->tickets->messages($row),
            'attachments' => $this->tickets->attachments($row),
        ]);
    }

    public function messages(Request $request, int $ticket): JsonResponse
    {
        $row = $this->find($request, $ticket);
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
        $row = $this->find($request, $ticket);
        $message = $this->tickets->reply($row, $request->toData(TicketActor::owner($this->owner($request))));

        return JsonResponder::created(__('support::tickets.sent'), [
            'html' => $this->messageHtml($message, $row),
            'last_id' => $message->id,
        ]);
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $row = $this->find($request, $ticket);
        $this->tickets->close($row, TicketActor::owner($this->owner($request)));

        return JsonResponder::success(__('support::tickets.closed', ['reference' => $row->displayReference()]), null, route('owner.support.tickets.show', $row->id));
    }

    public function reopen(Request $request, int $ticket): JsonResponse
    {
        $row = $this->find($request, $ticket);
        $this->tickets->reopen($row, TicketActor::owner($this->owner($request)));

        return JsonResponder::success(__('support::tickets.reopened', ['reference' => $row->displayReference()]), null, route('owner.support.tickets.show', $row->id));
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $file = $this->tickets->findAttachment($attachment) ?? throw new NotFoundHttpException;

        if (! Gate::forUser($this->owner($request))->allows('viewAttachment', [SupportTicket::class, $file])) {
            throw new NotFoundHttpException;
        }

        return $this->tickets->stream($file, $request->boolean('download'));
    }

    private function find(Request $request, int $ticket): SupportTicket
    {
        $row = $this->tickets->find($ticket, $this->tickets->ownerScope($this->owner($request)));
        Gate::forUser($this->owner($request))->authorize('reply', $row);

        return $row;
    }

    private function messageHtml(SupportTicketMessage $message, SupportTicket $ticket): string
    {
        return view('support::tickets.thread._message', ['message' => $message, 'ticket' => $ticket, 'viewer' => 'owner'])->render();
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
