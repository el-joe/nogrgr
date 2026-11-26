{{-- Row actions of VEN-080: preview (when a template exists), edit (view without manage), reset a customised template. --}}
@php($name = $row->event->label())
<div class="flex items-center justify-end gap-1">
    @if ($row->source() !== 'builtin' || $row->content() !== null)
        <x-ui::icon-button icon="eye" size="sm" :label="__('notifications::vendor_templates.preview_title', ['name' => $name])" :data-template-preview="vendor_route('settings.email-templates.preview', ['event' => $row->event->value])" />
    @endif
    <x-ui::icon-button :icon="$canManage ? 'edit' : 'file'" size="sm" :label="__($canManage ? 'notifications::vendor_templates.edit' : 'notifications::vendor_templates.view')" :href="vendor_route('settings.email-templates.edit', ['event' => $row->event->value])" />
    @if ($canManage && $row->isCustomized())
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="refresh" :data-confirm="__('notifications::vendor_templates.reset_message')" :data-confirm-title="__('notifications::vendor_templates.reset_title', ['name' => $name])" data-confirm-method="DELETE" :data-confirm-label="__('notifications::vendor_templates.reset')" :data-confirm-url="vendor_route('settings.email-templates.reset', ['event' => $row->event->value])">{{ __('notifications::vendor_templates.reset') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
