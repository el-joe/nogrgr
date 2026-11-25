{{--
    Master list of gateways (left column, Figma 51:17971) with client-side search.
    @param \Illuminate\Support\Collection $gateways
    @param \App\Modules\Integrations\Models\PaymentGateway|null $current
--}}
<x-ui::card :padding="false" class="p-3 lg:sticky lg:top-20">
    <label class="ui-control mb-2" for="gateway-search">
        <x-ui::icon name="search" :size="18" class="text-ink-muted" />
        <input id="gateway-search" type="search" data-gateway-search placeholder="{{ __('integrations::admin.gateways.search') }}" aria-label="{{ __('integrations::admin.gateways.search') }}">
    </label>
    <ul class="grid gap-1" data-gateway-list>
        @foreach ($gateways as $gateway)
            @php $selected = $current?->is($gateway); @endphp
            <li data-gateway-item data-search="{{ strtolower($gateway->code->brand().' '.$gateway->code->value) }}">
                <a href="{{ route('admin.integrations.gateways.edit', $gateway->code) }}" @class([
                    'flex items-center gap-3 rounded-control border px-2.5 py-2 transition-colors',
                    'border-primary bg-primary-subtle' => $selected,
                    'border-transparent hover:bg-subtle' => ! $selected,
                ]) @if ($selected) aria-current="page" @endif>
                    <span class="ui-gateway-tile grid size-8 shrink-0 place-items-center rounded-lg text-xs font-medium" data-gateway="{{ $gateway->code->value }}" aria-hidden="true">{{ $gateway->code->initials() }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ $gateway->code->brand() }}</span>
                    @include('integrations::admin.gateways._badge', ['gateway' => $gateway])
                </a>
            </li>
        @endforeach
    </ul>
    <p class="hidden px-2 py-6 text-center text-[13px] text-ink-secondary" data-gateway-empty>{{ __('integrations::admin.gateways.no_match') }}</p>
</x-ui::card>
