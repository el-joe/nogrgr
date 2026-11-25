@php
    $vendor = $surface === 'vendor';
    $showUrl = $vendor ? vendor_route('sales.refunds.show', ['refund' => $refund->id]) : route('admin.refunds.show', $refund->id);
    $formUrl = fn (string $action) => $vendor ? vendor_route('sales.refunds.action.form', ['refund' => $refund->id, 'action' => $action]) : route('admin.refunds.action.form', [$refund->id, $action]);
    $icons = ['retry' => 'refresh', 'complete' => 'check', 'reject' => 'x-circle'];
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" data-remote-modal="refund-modal" :data-url="$showUrl" :label="__('after-sales::refunds.view', ['reference' => $refund->reference])" />
    @if ($actions !== [])
        <x-ui::row-actions>
            @foreach ($actions as $action)
                <x-ui::dropdown-item :icon="$icons[$action]" :tone="$action === 'reject' ? 'danger' : null" data-remote-modal="refund-modal" :data-url="$formUrl($action)">{{ __('after-sales::refunds.actions.'.$action) }}</x-ui::dropdown-item>
            @endforeach
        </x-ui::row-actions>
    @endif
</div>
