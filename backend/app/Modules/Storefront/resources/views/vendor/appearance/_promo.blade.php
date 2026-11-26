{{-- Appearance › Promo banner (VEN-034c, Figma 85:107559): on / off, text per language, link, background, live preview. --}}
<form method="POST" action="{{ vendor_route('store.appearance.promo') }}" data-ajax data-appearance-form="promo" data-promo-form novalidate class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
    @csrf
    @method('PUT')
    <x-ui::card :title="__('content::store_appearance.promo.title')" :subtitle="__('content::store_appearance.promo.help')">
        <fieldset class="grid gap-5" @disabled(! $canManage)>
            <x-ui::switch name="enabled" :label="__('content::store_appearance.promo.enabled')" :checked="$promo->enabled" data-promo-enabled />
            <x-ui::locale-tabs id="promo-i18n" :locales="$locales">
                @foreach ($locales as $code => $language)
                    <x-ui::locale-pane parent="promo-i18n" :locale="$code" :active="array_key_first($locales)">
                        <x-ui::input :name="'text['.$code.']'" :label="__('content::store_appearance.promo.text')" :value="$promo->text[$code] ?? null" maxlength="160" :dir="$language['dir'] ?? 'ltr'" :required="$code === 'en'" data-promo-text="{{ $code }}" />
                    </x-ui::locale-pane>
                @endforeach
            </x-ui::locale-tabs>
            <x-ui::input name="link" :label="__('content::store_appearance.promo.link')" :value="$promo->link" maxlength="500" dir="ltr" placeholder="/pages/shipping-info" :helper="__('content::store_appearance.promo.link_help')" />
            <div class="grid gap-1.5">
                <label for="promo-background" class="text-[13px] font-medium text-ink">{{ __('content::store_appearance.promo.background') }}</label>
                <span class="flex items-center gap-2">
                    <input type="color" id="promo-background" name="background" value="{{ $promo->background }}" class="h-10 w-14 cursor-pointer rounded-control border border-line bg-field p-1" data-promo-background>
                    <span class="text-sm text-ink-secondary" dir="ltr" data-promo-background-hex>{{ $promo->background }}</span>
                </span>
                <p class="ui-error hidden text-xs text-danger" data-error-for="background"></p>
            </div>
        </fieldset>
    </x-ui::card>
    <x-ui::card :title="__('content::store_appearance.promo.preview')" class="lg:sticky lg:top-20">
        <div class="overflow-hidden rounded-card border border-line">
            <p class="bg-(--promo-bg,#0b0b0b) px-4 py-2 text-center text-sm text-(--promo-ink,#ffffff)" data-promo-preview data-bg="{{ $promo->background }}">{{ $promo->textFor(app()->getLocale()) ?? __('content::store_appearance.promo.text') }}</p>
            <div class="h-16 bg-subtle"></div>
        </div>
        @if ($canManage)
            <div class="mt-5 flex justify-end">
                <x-ui::button type="submit" icon="check">{{ __('content::store_appearance.save') }}</x-ui::button>
            </div>
        @endif
    </x-ui::card>
</form>
