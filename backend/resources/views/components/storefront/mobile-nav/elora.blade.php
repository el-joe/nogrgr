{{-- Elora mobile nav (rebuilt in Elora style): white bar, icon + label, active in brand orange. --}}
@props(['items' => [], 'active' => null])
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-sf-border bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="{{ __st('shell.mobile.label') }}">
    <ul class="grid h-16" style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr))" role="list">
        @foreach ($items as $key => $item)
            <li>
                <a href="{{ route($item['route']) }}" @class(['mo-press flex h-full flex-col items-center justify-center gap-1 text-xs', 'text-sf-accent' => $active === $key, 'text-sf-muted' => $active !== $key]) @if ($active === $key) aria-current="page" @endif>
                    <x-ui::icon :name="$item['icon']" :size="22" />{{ __st($item['label']) }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<div class="h-16 lg:hidden" aria-hidden="true"></div>
