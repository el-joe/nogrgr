{{--
    Admin › Logistics › Shipping › Rules › Create / Edit (ADM-032a, Figma Admin Panel 71:44486):
    rule card (name, countries, status) and one card per zone (cities + weight bands);
    add / remove zones and bands; overlapping bands are rejected with the Figma message.
--}}
@php
    $isNew = ! $rule->exists;
    $title = $isNew ? __('shipping::rules.create_title') : __('shipping::rules.edit_title', ['name' => $rule->name]);
    $zones = $isNew ? collect([null]) : $rule->zones;
    $citiesUrl = route('admin.shipping.rules.cities');
    $countryIds = $isNew ? [] : $rule->countries->pluck('id')->all();
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-shipping">
    <form id="rule-form" method="POST" action="{{ $isNew ? route('admin.shipping.rules.store') : route('admin.shipping.rules.update', $rule) }}" data-ajax data-unsaved-form data-rule-form data-cities-url="{{ $citiesUrl }}" novalidate class="grid gap-6">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::rules.breadcrumb') => route('admin.shipping.rules.index'), ($isNew ? __('shipping::rules.new') : $rule->name) => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.shipping.rules.index')">{{ __('shipping::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('shipping::rules.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::card :title="__('shipping::rules.rule')">
            <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_200px]">
                <x-ui::input name="name" :label="__('shipping::rules.fields.name')" :value="$rule->name" required maxlength="120" :placeholder="__('shipping::rules.fields.name_placeholder')" />
                <x-ui::select name="country_ids" multiple :label="__('shipping::rules.fields.countries')" :options="$countries" :selected="$countryIds" :placeholder="__('shipping::rules.fields.countries')" required data-rule-countries />
                <x-ui::select name="status" :label="__('shipping::rules.fields.status')" :options="$statuses" :selected="$rule->status?->value" :search="false" required />
            </div>
        </x-ui::card>

        <div class="grid items-start gap-6 lg:grid-cols-2" data-zones>
            @foreach ($zones as $z => $zone)
                @include('shipping::admin.rules._zone', ['z' => $z, 'zone' => $zone])
            @endforeach
        </div>
        <p data-zones-empty @if ($zones->isNotEmpty()) hidden @endif class="rounded-card border border-dashed border-line-strong p-6 text-center text-sm text-ink-secondary">{{ __('shipping::rules.no_zones') }}</p>
        <div>
            <x-ui::button variant="secondary" icon="plus" data-zone-add>{{ __('shipping::rules.add_zone') }}</x-ui::button>
        </div>

        <template data-zone-template>@include('shipping::admin.rules._zone', ['z' => '__Z__', 'zone' => null])</template>
        <template data-band-template>@include('shipping::admin.rules._band', ['z' => '__Z__', 'b' => '__B__', 'band' => null])</template>

        <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
            <p class="text-sm font-medium text-ink">{{ __('shipping::common.unsaved') }}</p>
            <div class="flex gap-3">
                <x-ui::button variant="secondary" data-unsaved-discard>{{ __('shipping::common.discard') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('shipping::rules.save') }}</x-ui::button>
            </div>
        </div>
    </form>
</x-layouts.panel>
