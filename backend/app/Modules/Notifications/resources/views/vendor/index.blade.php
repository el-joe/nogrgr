{{--
    Vendor › Notifications (VEN-090, Figma 12:17282): "n unread" pill, "Mark all as read",
    category chips and the server-side feed — each row opens its target (marks read) and
    unread rows carry "Mark as read". New rows arrive live (Reverb) through the bell.
--}}
<x-vendor::layout :title="__('notifications::panel.title')" page="vendor-notifications">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('notifications::panel.title')"
            :subtitle="__('notifications::panel.vendor_subtitle')"
            :breadcrumbs="[__('navigation.sections.system') => null, __('notifications::panel.title') => null]"
        >
            <x-slot:status>
                <x-ui::badge tone="brand" data-unread-pill :hidden="$unread === 0">{{ trans_choice('notifications::panel.unread_count', $unread, ['count' => $unread]) }}</x-ui::badge>
            </x-slot:status>
            <x-slot:actions>
                <form method="POST" action="{{ vendor_route('notifications.read-all') }}" data-ajax novalidate data-notification-mark-all-page @if ($unread === 0) hidden @endif>
                    @csrf
                    <x-ui::button type="submit" icon="check">{{ __('notifications::panel.inbox.mark_all_vendor') }}</x-ui::button>
                </form>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::datatable
            id="notifications"
            :url="vendor_route('notifications.data')"
            :columns="[
                ['data' => 'notification', 'title' => __('notifications::panel.inbox.columns.notification'), 'orderable' => false],
                ['data' => 'created_at', 'title' => __('notifications::panel.inbox.columns.received'), 'className' => 'hidden'],
            ]"
            :order="[[1, 'desc']]"
            :empty-title="__('notifications::panel.inbox.empty_title')"
            :empty-description="__('notifications::panel.inbox.vendor_empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="notifications" :search="false">
                    <input type="hidden" name="category" value="" data-filter data-filter-label="{{ __('notifications::panel.inbox.filter_label') }}" data-notification-category>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('notifications::panel.inbox.filter_label') }}">
                        <button type="button" data-category-chip="" aria-pressed="true" class="inline-flex h-8 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-[13px] font-medium text-ink transition-colors hover:bg-subtle aria-pressed:border-transparent aria-pressed:bg-inverse aria-pressed:text-on-inverse">{{ __('notifications::panel.inbox.all') }}</button>
                        @foreach ($categories as $category)
                            <button type="button" data-category-chip="{{ $category->value }}" aria-pressed="false" class="inline-flex h-8 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-[13px] font-medium text-ink transition-colors hover:bg-subtle aria-pressed:border-transparent aria-pressed:bg-inverse aria-pressed:text-on-inverse">{{ $category->label() }}</button>
                        @endforeach
                    </div>
                    <x-ui::select name="state" :label="__('notifications::panel.inbox.state')" label-hidden :options="\App\Modules\Notifications\Enums\ReadState::options()" :placeholder="__('notifications::panel.inbox.state')" :search="false" allow-clear data-filter :data-filter-label="__('notifications::panel.inbox.state')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-vendor::layout>
