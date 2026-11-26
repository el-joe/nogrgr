{{--
    Admin › System tools › Developer docs (ADM-115 index / ADM-115a topic, Figma 80:94467):
    topics card (icon + title, current highlighted) and the topic card (title, updated
    date, summary, sections with lists / code blocks with a copy button, info note).
--}}
@php
    /** @var array{title: string, summary: string, sections: list<array<string, mixed>>, note: string|null, updated: \Carbon\CarbonImmutable} $doc */
    $inline = fn (string $text) => preg_replace('/`([^`]+)`/', '<code class="rounded-[4px] bg-subtle px-1 py-0.5 font-mono text-[13px] text-ink" dir="ltr">$1</code>', e($text));
@endphp
<x-layouts.panel surface="admin" :title="__('platform::developer_docs.title').' · '.$doc['title']">
    <div class="grid gap-6 *:min-w-0">
        <x-ui::page-header
            :title="__('platform::developer_docs.title')"
            :breadcrumbs="[__('platform::developer_docs.breadcrumb_system') => null, __('navigation.admin.system_tools') => null, __('platform::developer_docs.title') => route('admin.system.docs.index'), $doc['title'] => null]"
        />

        <div class="grid items-start gap-4 lg:grid-cols-[296px_minmax(0,1fr)]">
            <nav class="rounded-card border border-line bg-surface p-3 shadow-card" aria-labelledby="docs-topics-title">
                <h2 id="docs-topics-title" class="px-2 pb-2 pt-1 text-base font-semibold text-ink">{{ __('platform::developer_docs.topics_title') }}</h2>
                <ul class="grid" role="list">
                    @foreach ($topics as $item)
                        @php $active = $item['topic'] === $current; @endphp
                        <li class="border-b border-line last:border-b-0">
                            <a href="{{ route('admin.system.docs.show', $item['topic']->value) }}" @if ($active) aria-current="page" @endif @class([
                                'flex min-h-12 items-center gap-3 rounded-control px-2 py-2 text-sm font-medium transition-colors',
                                'bg-primary-subtle text-ink-brand' => $active,
                                'text-ink hover:bg-subtle' => ! $active,
                            ])>
                                <span @class(['grid size-8 shrink-0 place-items-center rounded-control', 'bg-surface text-ink-brand' => $active, 'bg-subtle text-ink-secondary' => ! $active])><x-ui::icon :name="$item['icon']" :size="16" /></span>
                                {{ $item['title'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <article class="grid gap-5 rounded-card border border-line bg-surface p-5 shadow-card" aria-labelledby="doc-title">
                <header class="grid gap-1">
                    <h2 id="doc-title" class="text-lg font-semibold text-ink">{{ $doc['title'] }}</h2>
                    <p class="text-xs text-ink-secondary">{{ __('platform::developer_docs.updated', ['date' => $doc['updated']->locale(app()->getLocale())->isoFormat('D MMM YYYY')]) }}</p>
                </header>
                <p class="text-sm leading-6 text-ink">{!! $inline($doc['summary']) !!}</p>

                @foreach ($doc['sections'] as $section)
                    <section class="grid gap-3" aria-labelledby="doc-section-{{ $loop->index }}">
                        <h3 id="doc-section-{{ $loop->index }}" class="text-base font-semibold text-ink">{{ $section['heading'] }}</h3>
                        @foreach ($section['body'] ?? [] as $paragraph)
                            <p class="text-sm leading-6 text-ink-secondary">{!! $inline($paragraph) !!}</p>
                        @endforeach
                        @if (! empty($section['items']))
                            <ul class="grid list-disc gap-2 ps-5 text-sm leading-6 text-ink-secondary marker:text-ink-muted">
                                @foreach ($section['items'] as $entry)
                                    <li>{!! $inline($entry) !!}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($section['code']))
                            <div class="relative">
                                <pre class="overflow-x-auto rounded-control bg-inverse p-4 font-mono text-[13px] leading-6 text-on-inverse" dir="ltr"><code>{{ $section['code'] }}</code></pre>
                                <button type="button" class="absolute end-2 top-2 inline-flex h-8 items-center gap-1.5 rounded-chip bg-white/10 px-2.5 text-xs font-medium text-on-inverse hover:bg-white/20" data-copy-text="{{ $section['code'] }}">
                                    <x-ui::icon name="copy" :size="14" />{{ __('platform::developer_docs.copy') }}
                                </button>
                            </div>
                        @endif
                    </section>
                @endforeach

                @if ($doc['note'])
                    <p class="flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-sm text-ink" role="note">
                        <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
                        <span>{!! $inline($doc['note']) !!}</span>
                    </p>
                @endif
            </article>
        </div>
    </div>
</x-layouts.panel>
