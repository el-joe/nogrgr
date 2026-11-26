<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Support\DTOs\TicketUpdateData;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin ticket detail (ADM-116a) side card: status, priority and assignee selects; each
 * change is sent on its own. The assignee must be an admin allowed to view tickets.
 */
class TicketUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TicketPriority::class)],
            'assigned_admin_id' => ['sometimes', 'nullable', 'integer', Rule::in(app(AdminRepositoryContract::class)->activeWithPermission('support.tickets.view')->map(static fn (Admin $admin): int => (int) $admin->id)->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => (string) __('support::tickets.fields.status'),
            'priority' => (string) __('support::tickets.fields.priority'),
            'assigned_admin_id' => (string) __('support::tickets.fields.assignee'),
        ];
    }

    public function toData(): TicketUpdateData
    {
        $validated = $this->validated();
        $assignee = $validated['assigned_admin_id'] ?? null;

        return new TicketUpdateData(
            status: isset($validated['status']) ? TicketStatus::from((string) $validated['status']) : null,
            priority: isset($validated['priority']) ? TicketPriority::from((string) $validated['priority']) : null,
            assigneeGiven: array_key_exists('assigned_admin_id', $validated),
            assigneeId: $assignee !== null && $assignee !== '' ? (int) $assignee : null,
        );
    }
}
