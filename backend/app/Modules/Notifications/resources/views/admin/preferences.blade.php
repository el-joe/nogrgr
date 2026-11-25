{{--
    Admin › Notifications › Preferences (ADM-007a, Figma Admin Panel 80:96878):
    per-category switches for in-app, email and push.
--}}
<x-layouts.panel surface="admin" :title="__('notifications::panel.preferences.title')" data-page="admin-notifications">
    @include('notifications::admin._header')

    <x-ui::card :title="__('notifications::panel.preferences.title')" :subtitle="__('notifications::panel.preferences.subtitle')" id="notification-preferences" class="max-w-3xl">
        @include('notifications::admin._preferences-form', ['idPrefix' => 'pref'])
    </x-ui::card>
</x-layouts.panel>
