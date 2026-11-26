{{--
    Owner portal › Notifications (OWN-008, Figma Affiliate & Owner 122:105109): title with
    "Mark all as read", All / Unread tabs with counts and the server-side list — unread
    rows are tinted, each row opens its target (marks read). Live rows arrive through the bell.
--}}
<x-layouts.panel surface="owner" :title="__('notifications::panel.owner.title')" data-page="owner-notifications">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('notifications::panel.owner.title')" :subtitle="__('notifications::panel.owner.subtitle')">
            <x-slot:actions>
                <form method="POST" action="{{ route('owner.notifications.read-all') }}" data-ajax data-owner-mark-all @if ($unread === 0) hidden @endif>
                    @csrf
                    <x-ui::button type="submit" variant="secondary" icon="check">{{ __('notifications::panel.owner.mark_all') }}</x-ui::button>
                </form>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="flex gap-6 border-b border-line" role="tablist" aria-label="{{ __('notifications::panel.owner.title') }}">
            @foreach (['' => ['notifications::panel.owner.all', $total], 'unread' => ['notifications::panel.owner.unread', $unread]] as $value => [$label, $count])
                <button type="button" role="tab" data-state-tab="{{ $value }}" aria-selected="{{ $state === $value ? 'true' : 'false' }}"
                    class="-mb-px inline-flex items-center gap-2 border-b-2 border-transparent pb-2.5 text-sm font-medium text-ink-secondary transition-colors hover:text-ink aria-selected:border-primary aria-selected:text-ink-brand">
                    {{ __($label) }}
                    <span @class(['rounded-full bg-subtle px-1.5 text-xs text-ink-secondary']) data-state-count="{{ $value === '' ? 'all' : 'unread' }}">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        <x-ui::datatable
            id="owner-notifications"
            :url="route('owner.notifications.data')"
            :columns="[
                ['data' => 'notification', 'title' => __('notifications::panel.inbox.columns.notification'), 'orderable' => false],
                ['data' => 'created_at', 'title' => __('notifications::panel.inbox.columns.received'), 'className' => 'hidden'],
            ]"
            :order="[[1, 'desc']]"
            :empty-title="__('notifications::panel.owner.empty_title')"
            :empty-description="__('notifications::panel.owner.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="owner-notifications" :search="false">
                    <input type="hidden" name="state" value="{{ $state }}" data-filter data-filter-label="{{ __('notifications::panel.inbox.state') }}" data-owner-state>
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
