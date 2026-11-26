{{--
    Page header (Figma 31:10467): breadcrumb → H1 + status badge + subtitle → actions on
    the inline-end (primary last). Optional tabs slot (underline row) below.
--}}
@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<header {{ $attributes->class(['ui-page-header grid gap-4']) }} data-crumbs="{{ count($breadcrumbs) }}">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="grid min-w-0 gap-1">
            <x-ui::breadcrumbs :items="$breadcrumbs" class="mb-1" />
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="ui-page-title text-2xl font-semibold leading-8 text-ink">{{ $title }}</h1>
                {{ $status ?? '' }}
            </div>
            @if ($subtitle)
                <p class="ui-page-subtitle text-sm text-ink-secondary">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
        @endisset
    </div>
    {{ $tabs ?? '' }}
</header>
