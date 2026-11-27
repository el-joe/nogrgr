{{-- Store-page section "FAQ" (VEN-032): native <details> accordion (keyboard + screen reader friendly). --}}
@props(['section'])
<section class="grid gap-4 rounded-sf-card border border-sf-border bg-sf-surface p-4 md:p-10" aria-labelledby="ps-{{ $section['key'] }}" data-reveal="fade-up">
    <x-sf::page-section.heading :title="$section['title'] ?? __st('pages.sections.faq')" :id="'ps-'.$section['key']" />
    <div class="grid divide-y divide-sf-border">
        @foreach ($section['items'] as $item)
            <details class="group py-3" @if ($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium leading-6 text-sf-ink [&::-webkit-details-marker]:hidden">
                    <span>{{ $item['question'] }}</span>
                    <x-ui::icon name="chevron-down" :size="18" class="shrink-0 text-sf-muted transition-transform duration-200 group-open:rotate-180" />
                </summary>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-sf-muted">{{ $item['answer'] }}</p>
            </details>
        @endforeach
    </div>
</section>
