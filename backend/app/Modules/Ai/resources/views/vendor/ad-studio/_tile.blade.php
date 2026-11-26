{{-- One option tile of the studio wizard (Figma 12:15155 / 12:15160): radio + 38 disc icon + label; picked = solid primary. --}}
<label @class(['ads-tile', 'ads-tile-compact' => $compact ?? false])>
    <input type="radio" name="{{ $name }}" value="{{ $value }}" class="sr-only" @checked($checked ?? false)>
    @isset($icon)
        <span class="ads-tile-disc" aria-hidden="true"><x-ui::icon :name="$icon" :size="18" /></span>
    @endisset
    <span>{{ $label }}</span>
</label>
