{{--
    OWN-007 Add another store (Figma Affiliate & Owner 122:104860): store name, store
    address (live availability) and country; plan and payment follow in the standard
    sign-up steps with the owner's account already filled. Inputs: $countries,
    $baseDomain, $subdomainExample.
--}}
@php
    $steps = ['details', 'plan', 'payment', 'setup'];
    $slug = old('slug');
@endphp
<x-layouts.panel surface="owner" :title="__('platform::signup.add_store.title')" data-page="owner-add-store">
    <x-ui::page-header :title="__('platform::signup.add_store.title')" :subtitle="__('platform::signup.add_store.intro')" />

    <x-ui::card>
        <form method="POST" action="{{ route('owner.stores.store') }}" class="grid gap-6" data-add-store-form data-availability-url="{{ route('owner.stores.availability') }}" data-base-domain="{{ $baseDomain }}" data-checking="{{ __('platform::signup.add_store.checking') }}" novalidate>
            @csrf

            <ol class="flex items-center gap-3 overflow-x-auto" aria-label="{{ __('platform::signup.add_store.title') }}">
                @foreach ($steps as $index => $step)
                    <li @class(['flex items-center gap-3', 'flex-1' => ! $loop->last]) @if ($index === 0) aria-current="step" @endif>
                        <span @class([
                            'grid size-7 shrink-0 place-items-center rounded-full text-xs',
                            'bg-primary text-on-primary' => $index === 0,
                            'border border-line-strong text-ink-secondary' => $index > 0,
                        ])>{{ \Illuminate\Support\Number::format($index + 1, locale: \App\Shared\Support\Locale::numberLocale()) }}</span>
                        <span @class(['text-sm whitespace-nowrap', 'font-medium text-ink' => $index === 0, 'text-ink-secondary' => $index > 0])>{{ __('platform::signup.add_store.steps.'.$step) }}</span>
                        @unless ($loop->last)
                            <span class="h-0.5 min-w-8 flex-1 rounded-full bg-line" aria-hidden="true"></span>
                        @endunless
                    </li>
                @endforeach
            </ol>

            <div class="grid gap-5 md:grid-cols-2">
                <x-ui::input name="store_name" :label="__('platform::signup.add_store.store_name')" required maxlength="120" autocomplete="organization" data-store-name />
                <div class="grid gap-1.5">
                    <x-ui::input name="slug" :label="__('platform::signup.add_store.address')" required maxlength="63" dir="ltr" autocomplete="off" data-store-slug
                        :helper="__('platform::signup.add_store.address_hint', ['host' => $slug ? \App\Modules\Platform\Support\TenantSlug::subdomainHost($slug) : $subdomainExample])" />
                    <p class="text-xs" data-slug-status role="status" aria-live="polite" hidden></p>
                </div>
                <x-ui::select name="default_country_id" :label="__('platform::signup.add_store.country')" :options="$countries" :placeholder="__('platform::signup.add_store.select_country')" allow-clear />
            </div>

            <p class="flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-sm text-ink">
                <x-ui::icon name="info" :size="18" class="mt-px shrink-0 text-info" />{{ __('platform::signup.add_store.info') }}
            </p>

            <div class="flex flex-wrap justify-end gap-3">
                <x-ui::button variant="secondary" :href="route('central.tenant.stores')">{{ __('platform::signup.add_store.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="chevron-right">{{ __('platform::signup.add_store.continue') }}</x-ui::button>
            </div>
        </form>
    </x-ui::card>
</x-layouts.panel>
