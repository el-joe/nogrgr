{{--
    Tabs (Figma Tab 29:10316). variant: underline (12px padding, 2px brand indicator,
    24 gap) | segmented (6×12 in a 4px track) | pill (vendor analytics 12:16223: 40px
    pills, idle muted, active brand, swipeable row). Count pills optional. An underline strip
    wider than its column scrolls sideways (no scrollbar) with edge fades (QA-P5-015).
    items: ['general' => 'General', 'logs' => ['label' => 'Logs', 'count' => 3, 'href' => '…', 'disabled' => false]]
    With href items the tabs are links (aria-current); otherwise pair them with
    <x-ui::tab-panel tabs="<id>" key="…"> and the active tab is kept in ?<param>=.
    Count pills are live regions (data-live, see panels/ui/live.js): they refresh after
    a change made without a reload.
--}}
@props(['id', 'items' => [], 'active' => null, 'variant' => 'underline', 'param' => 'tab', 'label' => null])
@php
    $normalized = collect($items)->map(fn ($item) => is_array($item) ? $item : ['label' => $item])->all();
    $active ??= request()->query($param) && array_key_exists(request()->query($param), $normalized) ? request()->query($param) : array_key_first($normalized);
    $isLinks = collect($normalized)->contains(fn ($item) => isset($item['href']));
    $segmented = $variant === 'segmented';
    $pill = $variant === 'pill';
@endphp
<div @unless ($isLinks) data-tabs data-tabs-param="{{ $param }}" @endunless id="{{ $id }}" {{ $attributes->class(['min-w-0']) }}>
    <div @unless ($isLinks) role="tablist" @endunless aria-label="{{ $label ?? __('ui.tabs') }}" @unless ($segmented) data-tabs-scroll @endunless @class([
        'ui-tabs-underline flex gap-6 overflow-x-auto overflow-y-hidden overscroll-x-contain border-b border-line [scrollbar-width:none] [&::-webkit-scrollbar]:hidden data-fade-left:mask-l-from-[calc(100%-3rem)] data-fade-right:mask-r-from-[calc(100%-3rem)]' => ! $segmented && ! $pill,
        'ui-tabs-pill flex gap-2 overflow-x-auto overflow-y-hidden overscroll-x-contain py-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden data-fade-left:mask-l-from-[calc(100%-3rem)] data-fade-right:mask-r-from-[calc(100%-3rem)]' => $pill,
        'inline-flex gap-1 rounded-control bg-subtle p-1' => $segmented,
    ])>
        @foreach ($normalized as $key => $item)
            @php
                $selected = (string) $key === (string) $active;
                $classes = $pill
                    ? 'ui-tab-pill inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full bg-muted px-4 text-sm text-ink-secondary transition-colors hover:text-ink aria-selected:bg-primary aria-selected:font-medium aria-selected:text-on-primary aria-[current=page]:bg-primary aria-[current=page]:font-medium aria-[current=page]:text-on-primary'
                    : ($segmented
                    ? 'inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-chip px-3 text-[13px] text-ink-secondary transition-colors hover:text-ink aria-selected:bg-surface aria-selected:font-medium aria-selected:text-ink aria-selected:shadow-card aria-[current=page]:bg-surface aria-[current=page]:text-ink aria-[current=page]:shadow-card'
                    : 'ui-tab-underline relative -mb-px inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 border-transparent py-3 text-sm text-ink-secondary transition-colors hover:border-line-strong hover:text-ink aria-selected:border-primary aria-selected:text-ink-brand aria-[current=page]:border-primary aria-[current=page]:text-ink-brand');
            @endphp
            @if ($isLinks)
                <a href="{{ $item['href'] ?? '#' }}" class="group {{ $classes }}" @if ($selected) aria-current="page" @endif>
            @else
                <button type="button" role="tab" id="{{ $id }}-{{ $key }}-tab" aria-controls="{{ $id }}-{{ $key }}" aria-selected="{{ $selected ? 'true' : 'false' }}" tabindex="{{ $selected ? 0 : -1 }}" data-tab-key="{{ $key }}" class="group {{ $classes }}" @disabled($item['disabled'] ?? false)>
            @endif
                {{ $item['label'] }}
                @if (isset($item['count']))
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-subtle px-1.5 text-xs font-medium text-ink-secondary group-aria-selected:bg-primary-subtle group-aria-selected:text-ink-brand" dir="ltr" data-live="tab-count:{{ $id }}:{{ $key }}">{{ $item['count'] }}</span>
                @endif
                <span class="hidden size-1.5 rounded-full bg-danger group-data-error:inline-block" role="img" aria-label="{{ __('ui.has_errors') }}"></span>
            @if ($isLinks)
                </a>
            @else
                </button>
            @endif
        @endforeach
    </div>
    {{ $slot }}
</div>
