<?php

namespace App\Modules\Support\Http\Controllers\StoreApi;

use App\Modules\Engagement\Enums\SupportSubject;
use App\Modules\Support\Http\Requests\StoreApi\CustomerTicketActionRequest;
use App\Modules\Support\Http\Requests\StoreApi\CustomerTicketIndexRequest;
use App\Modules\Support\Http\Requests\StoreApi\CustomerTicketReplyRequest;
use App\Modules\Support\Http\Requests\StoreApi\OpenCustomerTicketRequest;
use App\Modules\Support\Http\Resources\StoreApi\CustomerTicketResource;
use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Services\CustomerTicketService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store API customer support tickets (app + theme account pages): options, list, open,
 * detail (marks store replies read), reply, close, and files behind temporary signed
 * URLs. Signed-in customer only (401); another customer's ticket → 404.
 */
class CustomerTicketController extends Controller
{
    public function __construct(private readonly CustomerTicketService $tickets) {}

    public function options(CustomerTicketActionRequest $request): JsonResponse
    {
        return JsonResponder::success('', [
            'topics' => collect(SupportSubject::storefrontOptions())->map(static fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'orders' => $this->tickets->recentOrders($request->customer()),
            'max_files' => 5,
            'max_file_kb' => 10240,
            'file_types' => explode(',', CustomerTicketActionRequest::MIMES),
        ]);
    }

    public function index(CustomerTicketIndexRequest $request): JsonResponse
    {
        $page = $this->tickets->list($request->customer(), $request->group(), $request->perPage());

        return JsonResponder::success('', [
            'items' => collect($page->items())->map(static fn (CustomerTicket $ticket): array => CustomerTicketResource::row($ticket))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function store(OpenCustomerTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->open($request->customer(), $request->toData());

        return JsonResponder::created(
            (string) __('support::customer_tickets.api.opened', ['reference' => $ticket->displayReference()]),
            ['ticket' => CustomerTicketResource::detail($ticket, $this->tickets->messages($ticket))],
            // Theme account page (session request): open the new ticket's conversation.
            $request->hasSession() && Route::has('storefront.support.show') ? route('storefront.support.show', ['ticket' => $ticket->id], false) : null,
        );
    }

    public function show(CustomerTicketActionRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->findForCustomer($request->customer(), $ticket);
        $this->tickets->openByCustomer($row);

        return JsonResponder::success('', ['ticket' => CustomerTicketResource::detail($row->refresh(), $this->tickets->messages($row))]);
    }

    public function reply(CustomerTicketReplyRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->findForCustomer($request->customer(), $ticket);
        $message = $this->tickets->reply($row, $request->toData());

        return JsonResponder::created((string) __('support::customer_tickets.api.replied'), [
            'message' => CustomerTicketResource::message($row, $message),
            'ticket' => CustomerTicketResource::row($row->refresh()),
        ]);
    }

    public function close(CustomerTicketActionRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->closeByCustomer($this->tickets->findForCustomer($request->customer(), $ticket), $request->customer());

        return JsonResponder::success((string) __('support::customer_tickets.api.closed', ['reference' => $row->displayReference()]), ['ticket' => CustomerTicketResource::row($row)]);
    }

    /**
     * A ticket file (temporary signed URL from the ticket detail).
     */
    public function attachment(Request $request, int $ticket, int $attachment): StreamedResponse
    {
        $row = $this->tickets->find($ticket);

        return $this->tickets->stream($this->tickets->attachment($attachment, $row), $request->boolean('download'));
    }
}
