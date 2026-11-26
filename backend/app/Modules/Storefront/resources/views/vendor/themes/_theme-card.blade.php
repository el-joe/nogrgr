{{--
    One theme / home-variant card of the vendor themes page (VEN-030, Figma 85:104756):
    catalogue preview image, name + Active / Selected pill, detail line, actions
    (Activate / Select form, "Use in countries…" modal, Preview in a new tab).
    $form = ['action', 'method', 'fields' => name => value|list, 'label'] or null.
--}}
@php $compact ??= false; @endphp
<article @class([
    'group flex flex-col overflow-hidden rounded-card border bg-surface shadow-card transition-shadow hover:shadow-md',
    'border-primary ring-1 ring-primary' => $selected,
    'border-line' => ! $selected,
]) @if ($selected) aria-current="true" @endif>
    <div @class(['relative overflow-hidden bg-subtle', 'aspect-[16/9]' => ! $compact, 'aspect-[16/8]' => $compact])>
        @if ($theme->preview_url)
            <img src="{{ $theme->preview_url }}" alt="{{ __('content::vendor_themes.preview_of', ['theme' => $theme->name]) }}" loading="lazy" decoding="async" class="size-full object-contain bg-media transition-transform duration-300 group-hover:scale-[1.02]">
        @else
            <span class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="palette" :size="28" /></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-3">
        <div class="grid gap-1">
            <div class="flex items-center justify-between gap-2">
                <h3 class="truncate text-sm font-semibold text-ink">{{ $name }}</h3>
                @if ($selected)
                    <x-ui::badge tone="brand">{{ $badge }}</x-ui::badge>
                @endif
            </div>
            <p class="line-clamp-2 text-xs text-ink-secondary">{{ $detail }}</p>
        </div>
        <div class="mt-auto flex flex-wrap items-center gap-2">
            @if ($form)
                <form method="POST" action="{{ $form['action'] }}" data-ajax class="contents">
                    @csrf
                    @if ($form['method'] !== 'POST')
                        @method($form['method'])
                    @endif
                    @foreach ($form['fields'] as $field => $value)
                        @foreach ((array) $value as $item)
                            <input type="hidden" name="{{ is_array($value) ? $field.'[]' : $field }}" value="{{ $item }}">
                        @endforeach
                    @endforeach
                    <x-ui::button type="submit" size="sm">{{ $form['label'] }}</x-ui::button>
                </form>
            @endif
            @if ($modalUrl)
                <x-ui::button size="sm" variant="secondary" data-remote-modal="vendor-theme-modal" :data-url="$modalUrl">{{ __('content::vendor_themes.use_in_countries') }}</x-ui::button>
            @endif
            <x-ui::icon-button size="sm" variant="ghost" icon="eye" :href="$previewUrl" target="_blank" rel="noopener" :label="__('content::vendor_themes.preview_of', ['theme' => $name])" class="ms-auto" />
        </div>
    </div>
</article>
