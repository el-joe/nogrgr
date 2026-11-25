{{--
    Admin › Notifications inbox (ADM-007, Figma Admin Panel 80:96878): category chips,
    server-side list (open, mark read / unread, delete), "Mark all read" and the
    preferences card beside it. New rows arrive live through Reverb (the bell script
    reloads this table).
--}}
<x-layouts.panel surface="admin" :title="__('notifications::panel.title')" data-page="admin-notifications">
    @include('notifications::admin._header')

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
        <x-ui::datatable
            id="notifications"
            :url="route('admin.notifications.data')"
            :columns="[
                ['data' => 'notification', 'title' => __('notifications::panel.inbox.columns.notification'), 'orderable' => false],
                ['data' => 'created_at', 'title' => __('notifications::panel.inbox.columns.received')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[1, 'desc']]"
            :empty-title="__('notifications::panel.inbox.empty_title')"
            :empty-description="__('notifications::panel.inbox.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="notifications" :search="false">
                    <input type="hidden" name="category" value="" data-filter :data-filter-label="__('notifications::panel.inbox.filter_label')" data-notification-category>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('notifications::panel.inbox.filter_label') }}">
                        <button type="button" data-category-chip="" aria-pressed="true" class="inline-flex h-8 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-[13px] font-medium text-ink transition-colors hover:bg-subtle aria-pressed:border-transparent aria-pressed:bg-inverse aria-pressed:text-on-inverse">{{ __('notifications::panel.inbox.all') }}</button>
                        @foreach ($categories as $category)
                            <button type="button" data-category-chip="{{ $category->value }}" aria-pressed="false" class="inline-flex h-8 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-[13px] font-medium text-ink transition-colors hover:bg-subtle aria-pressed:border-transparent aria-pressed:bg-inverse aria-pressed:text-on-inverse">
                                {{ $category->label() }}
                                @if ($unreadByCategory[$category->value] > 0)
                                    <span class="grid h-[18px] min-w-[18px] place-items-center rounded-full bg-primary px-1 text-[11px] font-semibold text-on-primary">{{ $unreadByCategory[$category->value] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                    <x-ui::select name="state" :label="__('notifications::panel.inbox.state')" label-hidden :options="\App\Modules\Notifications\Enums\ReadState::options()" :placeholder="__('notifications::panel.inbox.state')" :search="false" allow-clear data-filter :data-filter-label="__('notifications::panel.inbox.state')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>

        <x-ui::card :title="__('notifications::panel.preferences.title')" :subtitle="__('notifications::panel.preferences.subtitle')" id="notification-preferences">
            @include('notifications::admin._preferences-form', ['idPrefix' => 'inbox-pref'])
        </x-ui::card>
    </div>
</x-layouts.panel>
