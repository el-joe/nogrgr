{{--
    Vendor › Settings › Notifications (VEN-087, Figma Vendor Panel new screens 91:119215):
    "Notification preferences" with Save in the header and the event × channel matrix
    (In-app, Email, Push). Unchecked boxes post 0 through their hidden input.
--}}
<x-vendor::layout :title="__('notifications::panel.preferences.vendor_title')" page="vendor-notification-preferences">
    <form id="vendor-notification-preferences" method="POST" action="{{ vendor_route('settings.notifications.update') }}" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')
        <x-ui::page-header
            :title="__('notifications::panel.preferences.vendor_title')"
            :subtitle="__('notifications::panel.preferences.vendor_subtitle')"
            :breadcrumbs="[__('navigation.admin.settings') => null, __('navigation.vendor.notification_preferences') => null]"
        >
            <x-slot:actions>
                <x-ui::button type="submit" icon="check">{{ __('notifications::panel.preferences.save_short') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::card :padding="false" class="overflow-hidden">
            <div class="relative overflow-x-auto">
                <table data-lead class="vnd-table w-full text-[13px]">
                    <thead class="bg-subtle text-xs text-ink-secondary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('notifications::panel.preferences.event') }}</th>
                            @foreach ($channels as $channel)
                                <th scope="col" class="w-24 px-3 py-3 text-center font-medium" title="{{ __('notifications::panel.preferences.channel_help.'.$channel->value) }}">{{ $channel->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topics as $topic)
                            <tr class="border-t border-line">
                                <th scope="row" class="px-4 py-3 text-start font-normal text-ink">
                                    <span class="grid gap-0.5">
                                        <span class="text-sm">{{ $topic->label() }}</span>
                                        <span class="text-xs text-ink-secondary">{{ __('notifications::panel.preferences.topics_help.'.$topic->value) }}</span>
                                    </span>
                                </th>
                                @foreach ($channels as $channel)
                                    <td class="px-3 py-3 text-center">
                                        <x-ui::checkbox
                                            :name="'preferences['.$topic->value.']['.$channel->value.']'"
                                            :id="'vendor-pref-'.$topic->value.'-'.$channel->value"
                                            :checked="$matrix[$topic->value][$channel->value]"
                                            unchecked-value="0"
                                            :aria-label="__('notifications::panel.preferences.toggle', ['channel' => $channel->label(), 'category' => $topic->label()])"
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui::card>

        <ul class="grid gap-1 text-xs text-ink-secondary">
            @foreach ($channels as $channel)
                <li><span class="font-medium text-ink">{{ $channel->label() }}:</span> {{ __('notifications::panel.preferences.channel_help.'.$channel->value) }}</li>
            @endforeach
        </ul>
    </form>

    @if ($canManageStock)
        {{-- Store-wide low-stock threshold (decision #124): products without their own minimum stock alert at this level. --}}
        <form id="vendor-stock-threshold" method="POST" action="{{ vendor_route('settings.notifications.stock-threshold') }}" data-ajax novalidate class="mt-6">
            @csrf
            @method('PUT')
            <x-ui::card>
                <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                    <div class="grid gap-1">
                        <h2 class="text-base font-semibold text-ink">{{ __('inventory::alerts.threshold.title') }}</h2>
                        <p class="text-xs text-ink-secondary">{{ __('inventory::alerts.threshold.help') }}</p>
                        <div class="mt-2 max-w-48">
                            <x-ui::input name="low_stock_threshold" id="vendor-low-stock-threshold" type="number" min="0" :max="\App\Modules\Inventory\Services\Alerts\StockAlertService::MAX_THRESHOLD" :label="__('inventory::alerts.threshold.label')" :value="$stockThreshold" dir="ltr" inputmode="numeric" />
                        </div>
                    </div>
                    <x-ui::button type="submit" variant="secondary" icon="check">{{ __('inventory::alerts.threshold.save') }}</x-ui::button>
                </div>
            </x-ui::card>
        </form>
    @endif
</x-vendor::layout>
