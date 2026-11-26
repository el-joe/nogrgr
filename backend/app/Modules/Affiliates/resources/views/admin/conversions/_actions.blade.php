{{-- ADM-061 row actions: view, reverse (approved / paid), reject (pending). --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('affiliates::admin.conversions.view')" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.conversions.show', $conversion->id)" />
    @if ($canManage && $conversion->status->canReverse())
        <x-ui::icon-button icon="return" size="sm" :label="__('affiliates::admin.conversions.reverse')" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.conversions.reverse.form', $conversion->id)" />
    @endif
    @if ($canManage && $conversion->status->canReject())
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="x-circle" tone="danger"
                data-confirm-url="{{ route('admin.affiliates.conversions.reject', $conversion->id) }}"
                data-confirm-method="POST"
                data-confirm-title="{{ __('affiliates::admin.conversions.reject_title', ['reference' => $conversion->reference()]) }}"
                data-confirm="{{ __('affiliates::admin.conversions.reject_message') }}"
                data-confirm-label="{{ __('affiliates::admin.conversions.reject') }}"
                data-confirm-tone="danger">{{ __('affiliates::admin.conversions.reject') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
