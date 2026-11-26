{{--
    FAQs (Figma 12:41906, mobile 12:43700): tinted rounded panel with an accessible
    accordion (first item open; "−" when open, chevron when closed). Groups get a small
    heading when there is more than one. Data: $seo, $faqGroups.
--}}
@php
    $faqGroups = collect($faqGroups ?? [])->filter(fn ($group) => collect($group['items'] ?? [])->isNotEmpty())->values();
    $showGroupTitles = $faqGroups->count() > 1;
@endphp
<x-central::layout :seo="$seo" page="site-faqs">
    <section class="relative px-4 pt-6 pb-16 lg:pt-[93px] lg:pb-[72px]" aria-labelledby="faqs-title">
        <h1 id="faqs-title" class="sr-only">{{ __('content::site.faqs.heading') }}</h1>
        @if ($faqGroups->isEmpty())
            <x-central::empty icon="message" :title="__('content::site.faqs.empty_title')" :text="__('content::site.faqs.empty_text')"
                :action-url="Route::has('central.contact') ? route('central.contact') : null" :action-label="__('content::site.common.contact_us')" />
        @else
            <div class="mx-auto flex max-w-[701px] flex-col gap-8">
                @foreach ($faqGroups as $groupIndex => $group)
                    @php $groupLabel = $group['group'] instanceof \BackedEnum && method_exists($group['group'], 'label') ? $group['group']->label() : (string) ($group['group'] ?? ''); @endphp
                    <div class="rounded-2xl lg:bg-[#fdf7f6] lg:px-8 lg:py-[34px]" data-reveal="fade-up" x-data="{ open: {{ $groupIndex === 0 ? 0 : -1 }} }">
                        @if ($showGroupTitles && $groupLabel !== '')
                            <h2 class="mb-4 text-lg font-medium text-primary">{{ $groupLabel }}</h2>
                        @endif
                        <ul class="flex flex-col gap-1">
                            @foreach ($group['items'] as $faq)
                                @php $key = $groupIndex.'-'.$loop->index; @endphp
                                <li>
                                    <h3>
                                        <button type="button" id="faq-q-{{ $key }}" aria-controls="faq-a-{{ $key }}" :aria-expanded="(open === {{ $loop->index }}).toString()"
                                            @click="open = open === {{ $loop->index }} ? -1 : {{ $loop->index }}"
                                            class="flex w-full items-center justify-between gap-4 py-3 text-start font-faq text-sm font-semibold text-site-ink transition-colors hover:text-primary">
                                            <span>{{ $faq->question }}</span>
                                            <span class="grid size-6 shrink-0 place-items-center">
                                                @php $initiallyOpen = $groupIndex === 0 && $loop->first; @endphp
                                                @if ($initiallyOpen)
                                                    <x-ui::icon name="minus" :size="20" x-show="open === {{ $loop->index }}" />
                                                    <x-ui::icon name="chevron-up" :size="20" x-show="open !== {{ $loop->index }}" x-cloak />
                                                @else
                                                    <x-ui::icon name="minus" :size="20" x-show="open === {{ $loop->index }}" x-cloak />
                                                    <x-ui::icon name="chevron-up" :size="20" x-show="open !== {{ $loop->index }}" />
                                                @endif
                                            </span>
                                        </button>
                                    </h3>
                                    <div id="faq-a-{{ $key }}" role="region" aria-labelledby="faq-q-{{ $key }}" @class(['site-accordion__panel', 'is-open' => $groupIndex === 0 && $loop->first]) :class="open === {{ $loop->index }} && 'is-open'">
                                        <div>
                                            <div class="site-prose pb-3 font-faq text-xs font-light leading-6 lg:text-[13px]">{!! $faq->answer !!}</div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-central::layout>
