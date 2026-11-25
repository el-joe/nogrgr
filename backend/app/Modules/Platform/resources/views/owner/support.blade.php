{{--
    Owner portal › Help & support (OWN-009, owner portal design language — no Figma
    frame): the platform's support e-mail / phone and social channels from Admin ›
    Settings › General (ADM-101). Unconfigured channels are hidden; with none at all
    the page shows an empty state. Support tickets join in P9.
    Inputs: $contacts, $socials (OwnerSupportService::channels).
--}}
<x-layouts.panel surface="owner" :title="__('platform::owner.support.title')" data-page="owner-support">
    <x-ui::page-header :title="__('platform::owner.support.title')" :subtitle="__('platform::owner.support.subtitle')" />

    @if ($contacts === [] && $socials === [])
        <x-ui::card>
            <x-ui::empty-state icon="lifebuoy" :title="__('platform::owner.support.empty_title')" :description="__('platform::owner.support.empty_description')" />
        </x-ui::card>
    @else
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            @if ($contacts !== [])
                <x-ui::card :title="__('platform::owner.support.contacts_title')">
                    <ul class="grid gap-3 sm:grid-cols-2" data-support-contacts>
                        @foreach ($contacts as $contact)
                            <li>
                                <a href="{{ $contact['href'] }}" class="group flex items-start gap-3 rounded-card border border-line bg-surface p-4 transition-colors hover:border-primary hover:bg-primary-subtle focus-visible:outline-2 focus-visible:outline-primary" data-support-channel="{{ $contact['key'] }}" data-reveal>
                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-subtle text-ink-brand transition-colors group-hover:bg-primary group-hover:text-on-primary" aria-hidden="true">
                                        <x-ui::icon :name="$contact['icon']" :size="18" />
                                    </span>
                                    <span class="grid min-w-0 gap-0.5">
                                        <span class="text-sm font-medium text-ink">{{ __('platform::owner.support.'.$contact['key']) }}</span>
                                        <bdi dir="ltr" class="truncate text-sm text-ink-brand">{{ $contact['value'] }}</bdi>
                                        <span class="text-xs text-ink-secondary">{{ __('platform::owner.support.'.$contact['key'].'_hint') }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui::card>
            @endif

            @if ($socials !== [])
                <x-ui::card :title="__('platform::owner.support.socials_title')">
                    <ul class="grid gap-2" data-support-socials>
                        @foreach ($socials as $social)
                            <li>
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between gap-3 rounded-control px-3 py-2.5 text-sm text-ink transition-colors hover:bg-subtle focus-visible:outline-2 focus-visible:outline-primary">
                                    <span class="font-medium">{{ __('integrations::general.socials.'.$social['network']) }}</span>
                                    <x-ui::icon name="link" :size="16" class="text-ink-secondary" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui::card>
            @endif
        </div>
    @endif
</x-layouts.panel>
