<?php

namespace App\Modules\Support\Http\Controllers\Admin;

use App\Modules\Billing\Services\TenantLookupService;
use App\Modules\Identity\Models\Admin;
use App\Modules\Support\DataTables\SupportTicketsDataTable;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketUpdateData;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Http\Requests\StoreSearchRequest;
use App\Modules\Support\Http\Requests\TicketReplyRequest;
use App\Modules\Support\Http\Requests\TicketUpdateRequest;
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
 * Admin › Support tickets (ADM-116 list, ADM-116a detail; Figma 80:95200 / 80:96112):
 * tabs Open / Waiting on vendor / Resolved / Closed / All, server-side list with search
 * and priority / category / store / assignee filters, the live conversation (Reverb +
 * polling), replies and internal notes with attachments, status / priority / assignee
 * changes and Resolve. support.tickets.view reads, support.tickets.manage acts.
 */
class TicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'open');

        return view('support::admin.index', [
            'tab' => in_array($tab, ['open', 'waiting', 'resolved', 'closed', 'all'], true) ? $tab : 'open',
            'counts' => $this->tickets->tabCounts(),
            'priorities' => TicketPriority::options(),
            'categories' => TicketCategory::options(),
            'assignees' => ['none' => (string) __('support::tickets.filters.unassigned')] + $this->tickets->assigneeOptions(),
        ]);
    }

    public function data(Request $request, SupportTicketsDataTable $table): JsonResponse
    {
        return $table->for('admin', null, $this->admin($request)->can('support.tickets.manage'))
            ->toJsonFromRequest($request, SupportTicketsDataTable::FILTERS);
    }

    public function stores(StoreSearchRequest $request, TenantLookupService $lookup): JsonResponse
    {
        return response()->json($lookup->search($request->term(), $request->page()));
    }

    public function show(Request $request, int $ticket): View
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->admin($request))->authorize('view', $row);
        $this->tickets->openByAdmin($row);

        return view('support::admin.show', [
            'ticket' => $row,
            'messages' => $this->tickets->messages($row, null, true),
            'attachments' => $this->tickets->attachments($row, true),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'assignees' => $this->tickets->assigneeOptions(),
            'sla' => $this->tickets->sla($row),
            'realtime' => $this->tickets->realtimeConfig($row),
            'canManage' => $this->admin($request)->can('support.tickets.manage'),
            'canImpersonate' => $row->tenant_id !== null && $this->admin($request)->can('tenants.impersonate'),
        ]);
    }

    /**
     * New messages after ?after=<id> + current status (polling fallback / after a push).
     */
    public function messages(Request $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        $after = filter_var($request->query('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $messages = $this->tickets->messages($row, $after === false ? null : $after, true);

        if ($row->admin_unread) {
            $this->tickets->openByAdmin($row);
        }

        return JsonResponder::success('', [
            'html' => $messages->map(fn (SupportTicketMessage $message): string => $this->messageHtml($message, $row))->implode(''),
            'last_id' => (int) ($messages->last()->id ?? $after ?: 0),
            'status' => ['label' => $row->status->label(), 'tone' => $row->status->tone()],
        ]);
    }

    public function reply(TicketReplyRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->admin($request))->authorize('reply', $row);
        $message = $this->tickets->reply($row, $request->toData(TicketActor::admin($this->admin($request)), true));

        return JsonResponder::created(__($message->is_internal ? 'support::tickets.note_saved' : 'support::tickets.sent'), [
            'html' => $this->messageHtml($message, $row),
            'last_id' => $message->id,
        ]);
    }

    public function update(TicketUpdateRequest $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->admin($request))->authorize('reply', $row);
        $row = $this->tickets->update($row, $request->toData(), TicketActor::admin($this->admin($request)));

        return JsonResponder::success(__('support::tickets.updated', ['reference' => $row->displayReference()]), [
            'status' => ['label' => $row->status->label(), 'tone' => $row->status->tone(), 'value' => $row->status->value],
        ]);
    }

    public function resolve(Request $request, int $ticket): JsonResponse
    {
        $row = $this->tickets->find($ticket);
        Gate::forUser($this->admin($request))->authorize('reply', $row);
        $this->tickets->update($row, new TicketUpdateData(status: TicketStatus::Resolved), TicketActor::admin($this->admin($request)));

        return JsonResponder::success(__('support::tickets.resolved', ['reference' => $row->displayReference()]), null, route('admin.support.tickets.show', $row->id));
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $file = $this->tickets->findAttachment($attachment) ?? throw new NotFoundHttpException;
        Gate::forUser($this->admin($request))->authorize('viewAttachment', [SupportTicket::class, $file]);

        return $this->tickets->stream($file, $request->boolean('download'));
    }

    private function messageHtml(SupportTicketMessage $message, SupportTicket $ticket): string
    {
        return view('support::tickets.thread._message', ['message' => $message, 'ticket' => $ticket, 'viewer' => 'admin'])->render();
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }
}
