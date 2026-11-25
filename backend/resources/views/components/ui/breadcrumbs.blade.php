{{-- Breadcrumb (13px, chevrons): :items="['Catalog' => route(…), 'Products' => null]" — the last item is the current page. --}}
@props(['items' => []])
@if ($items)
    <nav aria-label="{{ __('ui.breadcrumb') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-1.5 text-[13px] leading-[18px]">
            @foreach ($items as $label => $url)
                <li class="flex items-center gap-1.5">
                    @if (! $loop->first)
                        <x-ui::icon name="chevron-right" :size="14" class="text-ink-muted" />
                    @endif
                    @if ($url && ! $loop->last)
                        <a href="{{ $url }}" class="text-ink-secondary hover:text-ink hover:underline">{{ $label }}</a>
                    @else
                        <span @class(['font-medium text-ink' => $loop->last, 'text-ink-secondary' => ! $loop->last]) @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
