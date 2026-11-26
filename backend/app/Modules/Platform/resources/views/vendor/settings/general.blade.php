{{--
    Vendor › Settings › General (VEN-071, Figma Vendor Panel 90:112042): Store card (name,
    support e-mail, default profit %, timezone) saved by the header button; Countries and
    Categories cards (read-only chips) with "Request change" (VEN-071a / 071b modals,
    91:119876) and the pending request strip until the Nogrgr team reviews it.
--}}
<x-vendor::layout :title="__('platform::vendor_general.title')" page="vendor-general">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('platform::vendor_general.title')" :breadcrumbs="[__('navigation.admin.settings') => null, __('platform::vendor_general.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button type="submit" form="vendor-general-form" icon="check">{{ __('platform::vendor_general.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <x-ui::card :title="__('platform::vendor_general.store')" id="vendor-general-store">
                <form id="vendor-general-form" method="POST" action="{{ vendor_route('settings.general.update') }}" data-ajax data-unsaved-form novalidate>
                    @csrf
                    @method('PUT')
                    <fieldset class="grid gap-5" @disabled(! $canManage)>
                        <x-ui::input name="store_name" :label="__('platform::vendor_general.fields.store_name')" :value="$settings['store_name']" required maxlength="120" />
                        <x-ui::input name="support_email" type="email" :label="__('platform::vendor_general.fields.support_email')" :value="$settings['support_email']" maxlength="191" dir="ltr" autocomplete="email" :helper="__('platform::vendor_general.fields.support_email_help')" />
                        <x-ui::input name="profit_percent" type="number" :label="__('platform::vendor_general.fields.profit_percent')" :value="$settings['profit_percent']" min="0" max="100" step="0.01" dir="ltr" required :helper="__('platform::vendor_general.fields.profit_percent_help')" />
                        <x-ui::select name="timezone" :label="__('platform::vendor_general.fields.timezone')" :options="$timezones" :selected="$settings['timezone']" required />
                    </fieldset>
                </form>
            </x-ui::card>

            <div class="grid gap-6">
                @foreach (['countries' => 'globe', 'categories' => 'layers'] as $type => $icon)
                    @php($card = $cards[$type])
                    <x-ui::card :title="__('platform::vendor_general.'.$type.'.title')" :id="'vendor-general-'.$type">
                        @if ($canManage)
                            <x-slot:actions>
                                @if ($card['pending'] === null)
                                    <x-ui::button variant="secondary" size="sm" :icon="$icon" :data-overlay-open="'vendor-'.$type.'-request'">{{ __('platform::vendor_general.request_change') }}</x-ui::button>
                                @else
                                    <x-ui::button variant="secondary" size="sm" :icon="$icon" disabled aria-disabled="true" :title="__('platform::vendor_general.pending_locked')">{{ __('platform::vendor_general.request_change') }}</x-ui::button>
                                @endif
                            </x-slot:actions>
                        @endif

                        @if ($card['current'] === [])
                            <p class="text-sm text-ink-secondary">{{ __('platform::vendor_general.'.$type.'.empty') }}</p>
                        @else
                            <ul class="flex flex-wrap gap-2" aria-label="{{ __('platform::vendor_general.'.$type.'.title') }}">
                                @foreach ($card['current'] as $name)
                                    <li class="rounded-full border border-line px-3 py-1.5 text-[13px] font-medium text-ink">{{ $name }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($card['pending'] !== null)
                            @php
                                $parts = [];
                                if ($card['pendingSummary']['add'] !== []) {
                                    $parts[] = __('platform::vendor_general.pending.add', ['items' => implode(__('platform::vendor_general.list_separator'), $card['pendingSummary']['add'])]);
                                }
                                if ($card['pendingSummary']['remove'] !== []) {
                                    $parts[] = __('platform::vendor_general.pending.remove', ['items' => implode(__('platform::vendor_general.list_separator'), $card['pendingSummary']['remove'])]);
                                }
                            @endphp
                            <p class="mt-4 flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-[13px] text-ink" role="status">
                                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0 text-info" />
                                <span>{{ __('platform::vendor_general.pending.line', ['changes' => implode(__('platform::vendor_general.list_separator'), $parts), 'ago' => $card['pending']->created_at?->locale(app()->getLocale())->diffForHumans()]) }}</span>
                            </p>
                        @endif
                    </x-ui::card>
                @endforeach
            </div>
        </div>
    </div>

    @if ($canManage)
        @foreach (['countries', 'categories'] as $type)
            @continue($cards[$type]['pending'] !== null)
            <x-ui::modal :id="'vendor-'.$type.'-request'" :title="__('platform::vendor_general.modal.'.$type.'.title')" close-on-success>
                <form id="vendor-{{ $type }}-request-form" method="POST" action="{{ vendor_route('settings.general.request', ['type' => $type]) }}" data-ajax novalidate class="grid gap-5">
                    @csrf
                    <x-ui::select :id="'request-'.$type.'-add'" name="add" multiple :label="__('platform::vendor_general.modal.'.$type.'.add')" :options="$cards[$type]['addOptions']" :placeholder="__('platform::vendor_general.modal.'.$type.'.add_placeholder')" />
                    <x-ui::select :id="'request-'.$type.'-remove'" name="remove" multiple :label="__('platform::vendor_general.modal.'.$type.'.remove')" :options="$cards[$type]['current']" :placeholder="__('platform::vendor_general.modal.'.$type.'.remove_placeholder')" />
                    <x-ui::textarea :id="'request-'.$type.'-reason'" name="reason" :label="__('platform::vendor_general.modal.reason')" maxlength="1000" :rows="4" />
                </form>
                <x-slot:footer>
                    <x-ui::button variant="secondary" data-overlay-close>{{ __('platform::vendor_general.modal.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" :form="'vendor-'.$type.'-request-form'">{{ __('platform::vendor_general.modal.send') }}</x-ui::button>
                </x-slot:footer>
            </x-ui::modal>
        @endforeach
    @endif
</x-vendor::layout>
