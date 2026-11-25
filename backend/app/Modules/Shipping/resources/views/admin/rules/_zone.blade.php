{{--
    One zone card of the rule form: name, cities (Select2 remote, cities of the rule's
    countries; none = whole country), weight bands table. $z = index (or __Z__ in the
    <template>), $zone = ShippingZone|null.
--}}
@php
    $bands = $zone?->bands ?? collect([null]);
    $cities = $zone?->cities ?? collect();
@endphp
<section data-zone class="rounded-card border border-line bg-surface p-5 shadow-card" aria-label="{{ __('shipping::rules.zone') }}">
    @if ($zone?->id)
        <input type="hidden" name="zones[{{ $z }}][id]" value="{{ $zone->id }}">
    @endif
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="grid min-w-0 flex-1 gap-3">
            <x-ui::input :name="'zones['.$z.'][name]'" :label="__('shipping::rules.fields.zone_name')" :value="$zone?->name" maxlength="120" required :placeholder="__('shipping::rules.fields.zone_placeholder')" />
            <x-ui::select :name="'zones['.$z.'][city_ids]'" multiple :label="__('shipping::rules.fields.cities')" :options="$cities->mapWithKeys(fn ($city) => [$city->id => $city->name])->all()" :selected="$cities->pluck('id')->all()" :placeholder="__('shipping::rules.fields.cities_all')" :helper="__('shipping::rules.fields.cities_help')" data-zone-cities />
        </div>
        <x-ui::icon-button icon="trash" size="sm" :label="__('shipping::rules.remove_zone')" data-zone-remove />
    </div>

    <div class="relative overflow-x-auto rounded-control border border-line">
        <table class="w-full min-w-[520px] text-sm">
            <thead class="bg-subtle text-xs text-ink-secondary">
                <tr>
                    <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('shipping::rules.fields.band_name') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-end font-medium">{{ __('shipping::rules.fields.min_weight') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-end font-medium">{{ __('shipping::rules.fields.max_weight') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-end font-medium">{{ __('shipping::rules.fields.price') }}</th>
                    <th scope="col" class="w-10 px-2 py-2.5"><span class="sr-only">{{ __('shipping::common.actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line" data-bands>
                @foreach ($bands as $b => $band)
                    @include('shipping::admin.rules._band', ['z' => $z, 'b' => $b, 'band' => $band])
                @endforeach
            </tbody>
        </table>
    </div>
    <p data-bands-empty @if ($bands->isNotEmpty()) hidden @endif class="mt-3 text-[13px] text-ink-secondary">{{ __('shipping::rules.no_bands') }}</p>
    <div class="mt-3 flex justify-end">
        <x-ui::button variant="secondary" size="sm" icon="plus" data-band-add>{{ __('shipping::rules.add_band') }}</x-ui::button>
    </div>
</section>
