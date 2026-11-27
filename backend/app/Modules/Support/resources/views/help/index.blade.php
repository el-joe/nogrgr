{{--
    Vendor › Help center (VEN-092 / VEN-092a, Figma Vendor Panel (new screens) 88:112298):
    search over every article, Topics card with article counts, and on the right either
    the search results, the articles of the picked topic or one article (topic · read
    time, sections, "Was this helpful?" vote, related articles, ticket CTA).
    $topics, $term, $activeTopic (HelpTopic|null), $articles (cards), $article (array|null), $vote (bool|null).
--}}
@php
    $canOpenTicket = (bool) auth('vendor')->user()?->can('support.manage');
    $pageTitle = $article['title'] ?? __('support::help.title');
@endphp
<x-vendor::layout :title="$pageTitle" page="vendor-help">
    <div class="grid gap-6" data-help-center>
        <x-ui::page-header :title="__('support::help.title')" :subtitle="__('support::help.subtitle')"
            :breadcrumbs="$article ? [__('support::help.title') => vendor_route('help.index'), $article['topic']->label() => vendor_route('help.index', ['topic' => $article['topic']->value]), $article['title'] => null] : [__('support::help.title') => null]" />

        <form method="GET" action="{{ vendor_route('help.index') }}" role="search" class="ui-control min-h-11 w-full" data-help-search>
            <x-ui::icon name="search" :size="18" class="text-ink-muted" />
            <label for="help-search" class="sr-only">{{ __('support::help.search') }}</label>
            <input id="help-search" type="search" name="q" value="{{ $term }}" maxlength="120" placeholder="{{ __('support::help.search_placeholder') }}" class="h-10 w-full min-w-0 bg-transparent text-sm outline-none" autocomplete="off">
            <x-ui::button type="submit" size="sm" variant="ghost">{{ __('support::help.search') }}</x-ui::button>
        </form>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
            <x-ui::card :title="__('support::help.topics')" id="help-topics">
                <ul class="grid" role="list">
                    @foreach ($topics as $row)
                        @php($active = $activeTopic === $row['topic'])
                        <li class="border-b border-line last:border-b-0">
                            <a href="{{ vendor_route('help.index', ['topic' => $row['topic']->value]) }}" @class(['-mx-2 flex items-center gap-3 rounded-control px-2 py-2.5 transition-colors hover:bg-subtle', 'bg-primary-subtle' => $active]) @if ($active) aria-current="page" @endif data-help-topic="{{ $row['topic']->value }}">
                                <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary" aria-hidden="true"><x-ui::icon :name="$row['icon']" :size="18" /></span>
                                <span class="grid min-w-0">
                                    <span class="truncate text-sm font-medium text-ink">{{ $row['title'] }}</span>
                                    <span class="text-xs text-ink-secondary">{{ trans_choice('support::help.articles_count', $row['count'], ['count' => $row['count']]) }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-ui::card>

            <div class="grid min-w-0 gap-6">
                @if ($article)
                    <x-ui::card id="help-article">
                        <article class="grid gap-5" data-help-article="{{ $article['slug'] }}">
                            <header class="grid gap-1">
                                <h2 class="text-lg font-semibold text-ink">{{ $article['title'] }}</h2>
                                <p class="text-xs text-ink-secondary">{{ $article['topic']->label() }} · {{ __('support::help.read_time', ['minutes' => $article['minutes']]) }}</p>
                            </header>
                            <p class="text-sm leading-6 text-ink">{{ $article['summary'] }}</p>

                            @foreach ($article['sections'] as $section)
                                <section class="grid gap-2" data-reveal>
                                    <h3 class="text-base font-semibold text-ink">{{ $section['heading'] }}</h3>
                                    @foreach ($section['body'] ?? [] as $paragraph)
                                        <p class="text-sm leading-6 text-ink">{{ $paragraph }}</p>
                                    @endforeach
                                    @if (! empty($section['steps']))
                                        <ol class="grid gap-2 ps-1" role="list">
                                            @foreach ($section['steps'] as $step)
                                                <li class="flex gap-3 text-sm leading-6 text-ink">
                                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-primary-subtle text-xs font-semibold text-ink-brand" aria-hidden="true">{{ $loop->iteration }}</span>
                                                    <span>{{ $step }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                    @if (! empty($section['tip']))
                                        <p class="flex gap-2 rounded-card bg-primary-subtle p-3 text-sm leading-6 text-ink" role="note">
                                            <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-ink-brand" />
                                            <span>{{ $section['tip'] }}</span>
                                        </p>
                                    @endif
                                    @if (! empty($section['link']['route']) && Route::has('vendor.'.$section['link']['route']))
                                        <div><x-ui::button variant="secondary" size="sm" icon="arrow-right" :href="vendor_route($section['link']['route'])">{{ $section['link']['label'] }}</x-ui::button></div>
                                    @endif
                                </section>
                            @endforeach

                            <footer class="grid gap-2 border-t border-line pt-4" data-help-feedback data-url="{{ vendor_route('help.feedback', ['slug' => $article['slug']]) }}" data-thanks-yes="{{ __('support::help.feedback.voted_yes') }}" data-thanks-no="{{ __('support::help.feedback.voted_no') }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-ink-brand">{{ __('support::help.feedback.question') }}</span>
                                    <x-ui::icon-button icon="thumbs-up" size="sm" :label="__('support::help.feedback.yes')" data-help-vote="1" :aria-pressed="$vote === true ? 'true' : 'false'" @class(['text-ink-brand' => $vote === true]) />
                                    <x-ui::icon-button icon="thumbs-up" size="sm" :label="__('support::help.feedback.no')" data-help-vote="0" :aria-pressed="$vote === false ? 'true' : 'false'" @class(['rotate-180', 'text-ink-brand' => $vote === false]) />
                                </div>
                                <p @class(['text-xs text-ink-secondary', 'hidden' => $vote === null]) role="status" data-help-feedback-status>{{ $vote === null ? '' : ($vote ? __('support::help.feedback.voted_yes') : __('support::help.feedback.voted_no')) }}</p>
                            </footer>
                        </article>
                    </x-ui::card>

                    @if ($article['related'] !== [])
                        <x-ui::card :title="__('support::help.related')">
                            <ul class="grid" role="list">
                                @foreach ($article['related'] as $related)
                                    <li class="border-b border-line last:border-b-0">
                                        <a href="{{ vendor_route('help.article', ['slug' => $related['slug']]) }}" class="flex items-center justify-between gap-3 py-2.5 text-sm font-medium text-ink hover:text-ink-brand">
                                            <span class="truncate">{{ $related['title'] }}</span>
                                            <x-ui::icon name="chevron-right" :size="16" class="shrink-0 text-ink-secondary rtl:rotate-180" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui::card>
                    @endif
                @else
                    <x-ui::card :title="$term !== '' ? __('support::help.results_title', ['term' => $term]) : $activeTopic?->label()" id="help-articles">
                        @if ($term !== '')
                            <p class="-mt-2 mb-3 text-xs text-ink-secondary" data-help-results-count>{{ trans_choice('support::help.results_count', count($articles), ['count' => count($articles)]) }}</p>
                        @endif
                        @forelse ($articles as $card)
                            <a href="{{ vendor_route('help.article', ['slug' => $card['slug']]) }}" class="-mx-2 grid gap-1 rounded-control border-b border-line px-2 py-3 transition-colors last:border-b-0 hover:bg-subtle" data-help-result="{{ $card['slug'] }}">
                                <span class="text-sm font-semibold text-ink">{{ $card['title'] }}</span>
                                <span class="text-xs text-ink-secondary">{{ $card['topic']->label() }} · {{ __('support::help.read_time', ['minutes' => $card['minutes']]) }}</span>
                                <span class="line-clamp-2 text-sm text-ink-secondary">{{ $card['summary'] }}</span>
                            </a>
                        @empty
                            <x-ui::empty-state icon="search" :title="__('support::help.no_results_title', ['term' => $term])" :description="__('support::help.no_results_description')" />
                        @endforelse
                    </x-ui::card>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-5 shadow-card">
                    <div class="grid gap-0.5">
                        <p class="text-sm font-semibold text-ink">{{ __('support::help.still_need_help') }}</p>
                        <p class="text-sm text-ink-secondary">{{ __('support::help.still_need_help_text') }}</p>
                    </div>
                    @if (auth('vendor')->user()?->can('support.view'))
                        <x-ui::button :variant="$canOpenTicket ? 'primary' : 'secondary'" icon="lifebuoy" :href="$canOpenTicket ? vendor_route('support.create') : vendor_route('support.index')">{{ $canOpenTicket ? __('support::help.open_ticket') : __('navigation.vendor.support') }}</x-ui::button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-vendor::layout>
