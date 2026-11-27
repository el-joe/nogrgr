{{-- Maintenance windows › row actions: view, edit (open windows), end now / cancel, delete (not while running). --}}
@php
    $canManage = (bool) auth('admin')->user()?->can('system.maintenance.manage');
    $status = $window->status();
    $isOpen = $status->isOpen();
    $isActive = $status === \App\Modules\Platform\Enums\MaintenanceWindowStatus::Active;
    $record = [
        'id' => $window->id,
        'url' => route('admin.system.maintenance.update', $window->id),
        'readonly' => ! ($canManage && $isOpen),
        'scope' => $window->scope->value,
        'tenant_id' => $window->tenant_id,
        'tenant_text' => $window->tenant_id ? ($window->tenant?->name ?? '#'.$window->tenant_id).($window->tenant?->slug ? ' ('.$window->tenant->slug.')' : '') : null,
        'title' => $window->title,
        'notes' => $window->notes,
        'message_en' => $window->message['en'] ?? '',
        'message_ar' => $window->message['ar'] ?? '',
        'allowed_ips' => implode("\n", $window->allowed_ips ?? []),
        'starts_at' => $window->starts_at->format('Y-m-d\TH:i'),
        'ends_at' => $window->ends_at?->format('Y-m-d\TH:i'),
    ];
@endphp
<div class="flex items-center justify-end gap-1">
    @if ($canManage && $isOpen)
        <x-ui::icon-button icon="edit" size="sm" :label="__('platform::maintenance.actions.edit_named', ['title' => $window->title])" data-maint-edit="{{ json_encode($record) }}" />
    @else
        <x-ui::icon-button icon="eye" size="sm" :label="__('platform::maintenance.actions.view')" data-maint-edit="{{ json_encode($record) }}" />
    @endif
    @if ($canManage && ($isOpen || ! $isActive))
        <x-ui::row-actions :label="__('platform::maintenance.actions.more', ['title' => $window->title])">
            @if ($isOpen)
                <x-ui::dropdown-item
                    icon="x"
                    :data-confirm="__($isActive ? 'platform::maintenance.confirm.end_message' : 'platform::maintenance.confirm.cancel_message')"
                    :data-confirm-title="__($isActive ? 'platform::maintenance.confirm.end_title' : 'platform::maintenance.confirm.cancel_title', ['title' => $window->title])"
                    :data-confirm-label="__($isActive ? 'platform::maintenance.actions.end' : 'platform::maintenance.actions.cancel')"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.system.maintenance.end', $window->id)"
                >{{ __($isActive ? 'platform::maintenance.actions.end' : 'platform::maintenance.actions.cancel') }}</x-ui::dropdown-item>
            @endif
            @if (! $isActive)
                @if ($isOpen)
                    <x-ui::dropdown-divider />
                @endif
                <x-ui::dropdown-item
                    icon="trash"
                    tone="danger"
                    :data-confirm="__('platform::maintenance.confirm.delete_message')"
                    :data-confirm-title="__('platform::maintenance.confirm.delete_title', ['title' => $window->title])"
                    :data-confirm-label="__('platform::maintenance.actions.delete')"
                    data-confirm-method="DELETE"
                    :data-confirm-url="route('admin.system.maintenance.destroy', $window->id)"
                >{{ __('platform::maintenance.actions.delete') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
