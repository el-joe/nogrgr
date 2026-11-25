{{--
    Breadcrumb trail (Figma blog post 127:12209: 12px gray › orange current).
    items: list<array{name: string, url: ?string}> — usually $seo->breadcrumbs.
--}}
@props(['items' => []])
@if (count($items) > 1)
    <nav {{ $attributes->class(['text-xs']) }} aria-label="{{ __('content::site.blog.breadcrumbs') }}">
        <ol class="flex flex-wrap items-center gap-1.5 text-site-gray">
            @foreach ($items as $item)
                <li class="flex items-center gap-1.5">
                    @if (! $loop->last && ! empty($item['url']))
                        <a href="{{ $item['url'] }}" class="transition-colors hover:text-primary">{{ $item['name'] }}</a>
                        <x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" />
                    @else
                        <span class="text-primary" aria-current="page">{{ $item['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
