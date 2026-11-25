<div class="flex items-center justify-end">
    <x-ui::icon-button icon="eye" size="sm" :label="__('identity::audit.view')" data-remote-modal="audit-modal" :data-url="route('admin.system.audit.show', $event->id)" />
</div>
