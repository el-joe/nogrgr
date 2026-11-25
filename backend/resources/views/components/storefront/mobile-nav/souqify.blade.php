{{-- Souqify mobile nav (Figma 1:80723): light bar, active item = solid blue block. --}}
@props(['items' => [], 'active' => null])
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-sf-border bg-[#f7f8fa] pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="{{ __('storefront::shell.mobile.label') }}">
    <ul class="grid h-[68px]" style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr))" role="list">
        @foreach ($items as $key => $item)
            <li class="flex">
                <a href="{{ route($item['route']) }}" @class(['mo-press flex w-full flex-col items-center justify-center gap-1 text-xs', 'bg-sf-accent text-white' => $active === $key, 'text-sf-muted' => $active !== $key]) @if ($active === $key) aria-current="page" @endif>
                    <x-ui::icon :name="$item['icon']" :size="22" />{{ __($item['label']) }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<div class="h-[68px] lg:hidden" aria-hidden="true"></div>
