{{-- Appearance › Footer (VEN-034e, Figma 85:107559): about text + copyright per language, social icons switch; custom footer links below. --}}
<div class="grid gap-6">
    <form method="POST" action="{{ vendor_route('store.appearance.footer') }}" data-ajax data-appearance-form="footer" novalidate>
        @csrf
        @method('PUT')
        <x-ui::card :title="__('content::store_appearance.footer.title')" :subtitle="__('content::store_appearance.footer.help')">
            <fieldset class="grid gap-5" @disabled(! $canManage)>
                <x-ui::locale-tabs id="footer-i18n" :locales="$locales">
                    @foreach ($locales as $code => $language)
                        <x-ui::locale-pane parent="footer-i18n" :locale="$code" :active="array_key_first($locales)">
                            <div class="grid gap-5">
                                <x-ui::textarea :name="'about['.$code.']'" :label="__('content::store_appearance.footer.about')" :value="$footer->about[$code] ?? null" maxlength="300" rows="2" :helper="__('content::store_appearance.footer.about_help')" />
                                <x-ui::input :name="'copyright['.$code.']'" :label="__('content::store_appearance.footer.copyright')" :value="$footer->copyright[$code] ?? null" maxlength="160" :dir="$language['dir'] ?? 'ltr'" :helper="__('content::store_appearance.footer.copyright_help')" />
                            </div>
                        </x-ui::locale-pane>
                    @endforeach
                </x-ui::locale-tabs>
                <x-ui::switch name="show_social" :label="__('content::store_appearance.footer.show_social')" :description="__('content::store_appearance.footer.show_social_help')" :checked="$footer->showSocial" />
                @if ($canManage)
                    <div class="flex justify-end">
                        <x-ui::button type="submit" icon="check">{{ __('content::store_appearance.save') }}</x-ui::button>
                    </div>
                @endif
            </fieldset>
        </x-ui::card>
    </form>
    @include('storefront::vendor.appearance._links', ['placement' => 'footer', 'links' => $footerLinks])
</div>
