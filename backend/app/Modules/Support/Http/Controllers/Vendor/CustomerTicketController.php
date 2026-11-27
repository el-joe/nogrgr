<?php

namespace App\Modules\Support\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\DataTables\CustomerTicketsDataTable;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\Http\Requests\CustomerTicketStatusRequest;
use App\Modules\Support\Http\Requests\TicketReplyRequest;
use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketMessage;
use App\Modules\Support\Services\CustomerTicketService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Support › Customers: the store's customer tickets (server-side list, search +
 * status) beside the open conversation (shared tickets/thread/_conversation, 15 s polling),
 * reply with files, resolve / close / reopen. support.view reads, support.manage writes.
 */
class CustomerTicketController extends Controller
{
    public function __construct(private readonly CustomerTicketService $tickets) {}

    public function index(Request $request): View
    {
        $ticket = $this->tickets->latest();

        if ($ticket !== null) {
            $this->tickets->openByStore($ticket);
        }

        return $this->page($request, $ticket);
    }

    public function data(Request $request, CustomerTicketsDataTable $table): JsonResponse
    {
        $selected = filter_var($request->query('selected'), FILTER_VALIDATE_INT);

        return $table->selected($selected === false ? null : $selected)->toJsonFromRequest($request, CustomerTicketsDataTable::FILTERS);
    }

    public function show(Request $request, int $ticket): View
    {
        $row = $this->tickets->find($ticket);
        $this->tickets->openByStore($row);

        return $this->page($request, $row);
    }

    public function messages(Request $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        $after = filter_var($request->query('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $messages = $this->tickets->messages($row, $after === false ? null : $after);
        $this->tickets->openByStore($row);

        return JsonResponder::success('', [
            'html' => $messages->map(fn (CustomerTicketMessage $message): string => $this->messageHtml($message, $row))->implode(''),
            'last_id' => (int) ($messages->last()->id ?? $after ?: 0),
            'status' => ['label' => $row->status->label(), 'tone' => $row->status->tone()],
        ]);
    }

    public function reply(TicketReplyRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        $message = $this->tickets->reply($row, $request->toData(TicketActor::vendor($this->staff($request))));

        return JsonResponder::created(__('support::customer_tickets.sent'), [
            'html' => $this->messageHtml($message, $row),
            'last_id' => $message->id,
        ]);
    }

    public function status(CustomerTicketStatusRequest $request, int $ticket, string $status): JsonResponse
    {
        $row = $this->tickets->changeStatus($this->tickets->find($ticket), $request->status(), TicketActor::vendor($this->staff($request)));

        return JsonResponder::success(
            __('support::customer_tickets.status_saved', ['reference' => $row->displayReference(), 'status' => $row->status->label()]),
            null,
            vendor_route('support.customers.show', ['ticket' => $row->id]),
        );
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        return $this->tickets->stream($this->tickets->attachment($attachment), $request->boolean('download'));
    }

    private function page(Request $request, ?CustomerTicket $ticket): View
    {
        return view('support::customers.index', [
            'ticket' => $ticket,
            'messages' => $ticket !== null ? $this->tickets->messages($ticket) : collect(),
            'canManage' => $this->staff($request)->can('support.manage'),
        ]);
    }

    private function messageHtml(CustomerTicketMessage $message, CustomerTicket $ticket): string
    {
        return view('support::tickets.thread._message', ['message' => $message, 'ticket' => $ticket, 'viewer' => 'store'])->render();
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
