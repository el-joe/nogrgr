{{-- Shared header of the inbox and preferences tabs (Figma Admin Panel 80:96878). --}}
<x-ui::page-header
    :title="__('notifications::panel.title')"
    :subtitle="__('notifications::panel.subtitle')"
    :breadcrumbs="[__('navigation.sections.system') => null, __('notifications::panel.title') => null]"
>
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.notifications.read-all') }}" data-ajax novalidate data-notification-mark-all-page @if ($unread === 0) hidden @endif>
            @csrf
            <x-ui::button type="submit" variant="secondary" icon="check">{{ __('notifications::panel.inbox.mark_all') }}</x-ui::button>
        </form>
    </x-slot:actions>
    <x-slot:tabs>
        <x-ui::tabs id="notifications-tabs" :active="$tab" :label="__('notifications::panel.title')" :items="[
            'inbox' => ['label' => __('notifications::panel.tabs.inbox'), 'count' => $unread, 'href' => route('admin.notifications.index')],
            'preferences' => ['label' => __('notifications::panel.tabs.preferences'), 'href' => route('admin.notifications.preferences')],
        ]" />
    </x-slot:tabs>
</x-ui::page-header>
