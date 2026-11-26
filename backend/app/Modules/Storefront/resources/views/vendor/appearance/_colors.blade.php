{{--
    Appearance › Colours & fonts (VEN-034b, Figma 85:107559): brand palette (36 swatches) +
    custom colour, "theme default", LTR / RTL body font, live preview; Reset (theme defaults)
    and Save. Swatch colours are set from data-swatch by vendor/appearance.js (no inline style).
--}}
@php($current = $colors->primary)
<form method="POST" action="{{ vendor_route('store.appearance.colors') }}" data-ajax data-appearance-form="colors" data-colors-form novalidate class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
    @csrf
    @method('PUT')
    <x-ui::card :title="__('content::store_appearance.colors.title')">
        <div class="grid gap-6">
            <fieldset class="grid gap-3" @disabled(! $canManage)>
                <legend class="mb-3 text-[13px] font-medium text-ink">{{ __('content::store_appearance.colors.palette') }}</legend>
                <div class="flex flex-wrap gap-2.5" role="radiogroup" aria-label="{{ __('content::store_appearance.colors.primary') }}">
                    <label class="relative grid size-8 cursor-pointer place-items-center rounded-full border border-line bg-surface text-ink-muted has-checked:ring-2 has-checked:ring-ink has-checked:ring-offset-2 has-checked:ring-offset-surface" title="{{ __('content::store_appearance.colors.theme_default') }}">
                        <input type="radio" name="primary" value="" class="sr-only" @checked($current === null) data-color-choice>
                        <x-ui::icon name="refresh" :size="14" />
                        <span class="sr-only">{{ __('content::store_appearance.colors.theme_default') }}</span>
                    </label>
                    @foreach ($palette as $hex)
                        <label class="relative size-8 cursor-pointer rounded-full border border-black/10 bg-(--swatch) has-checked:ring-2 has-checked:ring-ink has-checked:ring-offset-2 has-checked:ring-offset-surface" data-swatch="{{ $hex }}" title="{{ $hex }}">
                            <input type="radio" name="primary" value="{{ $hex }}" class="sr-only" @checked($current === $hex) data-color-choice>
                            <span class="sr-only">{{ __('content::store_appearance.colors.pick', ['color' => $hex]) }}</span>
                        </label>
                    @endforeach
                    @php($custom = $current !== null && ! in_array($current, $palette, true))
                    <label class="relative size-8 cursor-pointer rounded-full border border-black/10 bg-(--swatch) has-checked:ring-2 has-checked:ring-ink has-checked:ring-offset-2 has-checked:ring-offset-surface" data-swatch="{{ $custom ? $current : '#ffffff' }}" data-custom-swatch @if (! $custom) hidden @endif>
                        <input type="radio" name="primary" value="{{ $custom ? $current : '' }}" class="sr-only" @checked($custom) data-color-choice data-custom-choice>
                        <span class="sr-only">{{ __('content::store_appearance.colors.custom') }}</span>
                    </label>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="grid gap-1.5 text-[13px] font-medium text-ink" for="appearance-custom-color">
                        {{ __('content::store_appearance.colors.custom') }}
                        <span class="flex items-center gap-2">
                            <input type="color" id="appearance-custom-color" value="{{ $current ?? '#ff4d00' }}" class="h-10 w-14 cursor-pointer rounded-control border border-line bg-field p-1" data-custom-color aria-describedby="appearance-custom-color-help">
                            <span class="text-sm font-normal text-ink-secondary" dir="ltr" data-custom-hex>{{ $current ?? '' }}</span>
                        </span>
                    </label>
                    <p id="appearance-custom-color-help" class="text-xs text-ink-secondary">{{ __('content::store_appearance.colors.custom_help') }}</p>
                </div>
                <p class="ui-error hidden text-xs text-danger" data-error-for="primary"></p>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui::select name="font_ltr" :label="__('content::store_appearance.colors.font_ltr')" :options="$ltrFonts" :selected="$colors->fontLtr?->value" :placeholder="__('content::store_appearance.colors.font_default')" allow-clear :search="false" :disabled="! $canManage" data-font-select="ltr" />
                <x-ui::select name="font_rtl" :label="__('content::store_appearance.colors.font_rtl')" :options="$rtlFonts" :selected="$colors->fontRtl?->value" :placeholder="__('content::store_appearance.colors.font_default')" allow-clear :search="false" :disabled="! $canManage" data-font-select="rtl" />
            </div>
            <p class="-mt-3 text-xs text-ink-secondary">{{ __('content::store_appearance.colors.font_help') }}</p>
        </div>
    </x-ui::card>

    <x-ui::card :title="__('content::store_appearance.colors.preview')" class="lg:sticky lg:top-20">
        <div class="grid gap-3 rounded-card border border-line bg-white p-5 text-[#121212]" data-color-preview>
            <p class="text-lg font-semibold">{{ __('content::store_appearance.colors.preview_heading') }}</p>
            <p class="text-sm text-[#5e5e5e]">{{ __('content::store_appearance.colors.preview_text') }}</p>
            <span class="inline-flex h-10 w-fit items-center rounded-full bg-(--preview-brand,#ff4d00) px-5 text-sm font-medium text-(--preview-ink,#ffffff)" data-preview-button>{{ __('content::store_appearance.colors.preview_button') }}</span>
        </div>
        @if ($canManage)
            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <x-ui::button variant="secondary" icon="refresh" :data-confirm="__('content::store_appearance.colors.reset_message')" :data-confirm-title="__('content::store_appearance.colors.reset_title')" data-confirm-method="DELETE" data-confirm-tone="primary" :data-confirm-label="__('content::store_appearance.colors.reset')" :data-confirm-url="vendor_route('store.appearance.colors.reset')" :disabled="$colors->isDefault()">{{ __('content::store_appearance.colors.reset') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('content::store_appearance.save') }}</x-ui::button>
            </div>
        @endif
    </x-ui::card>
</form>
