{{-- Store-page section "Rich text" (VEN-032): optional title + sanitised body (server allow-list). --}}
@props(['section'])
<section class="rounded-sf-card border border-sf-border bg-sf-surface p-4 md:p-10" aria-labelledby="ps-{{ $section['key'] }}" data-reveal="fade-up">
    <x-sf::page-section.heading :title="$section['title']" :id="'ps-'.$section['key']" class="mb-4" />
    <div class="sf-cms-body flex flex-col gap-4 text-sm leading-6 text-sf-muted [&_a]:text-sf-accent [&_a]:underline [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-sf-ink [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-sf-ink [&_li]:ms-5 [&_ol]:list-decimal [&_strong]:font-medium [&_strong]:text-sf-ink [&_ul]:list-disc">
        {!! $section['body'] !!}
    </div>
</section>
