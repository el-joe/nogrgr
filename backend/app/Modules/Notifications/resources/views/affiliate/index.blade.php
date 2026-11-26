{{--
    Affiliate › Notifications (AFF-014, Figma Affiliate & Owner 121:102771): server-side
    inbox (open, mark read / unread, delete), read-state filter and "Mark all read".
    New rows arrive live through Reverb (the bell script reloads this table). $unread.
--}}
<x-layouts.panel surface="affiliate" :title="__('notifications::panel.title')" data-page="affiliate-notifications">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('notifications::panel.title')" :subtitle="__('affiliates::panel.notifications.subtitle')">
            <x-slot:actions>
                <form method="POST" action="{{ route('affiliate.notifications.read-all') }}" data-ajax data-notification-mark-all-page @if ($unread === 0) hidden @endif>
                    @csrf
                    <x-ui::button type="submit" variant="secondary" icon="check">{{ __('notifications::panel.inbox.mark_all') }}</x-ui::button>
                </form>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::datatable
            id="notifications"
            :url="route('affiliate.notifications.data')"
            :columns="[
                ['data' => 'notification', 'title' => __('notifications::panel.inbox.columns.notification'), 'orderable' => false],
                ['data' => 'created_at', 'title' => __('notifications::panel.inbox.columns.received')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[1, 'desc']]"
            :empty-title="__('notifications::panel.inbox.empty_title')"
            :empty-description="__('affiliates::panel.notifications.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="notifications" :search="false">
                    <x-ui::select name="state" :label="__('notifications::panel.inbox.state')" label-hidden :options="\App\Modules\Notifications\Enums\ReadState::options()" :placeholder="__('notifications::panel.inbox.state')" :search="false" allow-clear data-filter :data-filter-label="__('notifications::panel.inbox.state')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
