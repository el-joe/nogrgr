{{--
    VEN-034a Appearance › Logo › AI logo generator (200:34905 aligned to Tenant Panel):
    store name, tagline, style, colours → "Generate 4 logos" → four options; picking one
    and "Use as store logo" replaces the store logo. States: idle, generating, options,
    limit reached (429), provider error. panels/vendor/ai-logo.js drives it.
    Data (view composer): $aiLogo {used_label, quota, store_name, styles, colors, urls} or null.
--}}
@php($aiLogo ??= null)
@if ($aiLogo !== null)
    <x-ui::card :title="__('ai::tools.logo.title')" :subtitle="$aiLogo['used_label']" class="lg:col-span-2">
        <form class="grid gap-5" data-ai-logo data-generate-url="{{ $aiLogo['urls']['generate'] }}" data-apply-url="{{ $aiLogo['urls']['apply'] }}"
            data-text-generic="{{ __('ai::tools.errors.generic') }}" data-text-option="{{ __('ai::tools.logo.option', ['number' => '__N__']) }}" data-text-again="{{ __('ai::tools.logo.regenerate') }}" novalidate>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui::input name="store_name" :label="__('ai::tools.logo.store_name')" :value="$aiLogo['store_name']" maxlength="60" required />
                <x-ui::input name="tagline" :label="__('ai::tools.logo.tagline')" maxlength="80" />
                <x-ui::select name="style" :label="__('ai::tools.logo.style')" :options="$aiLogo['styles']" :selected="array_key_first($aiLogo['styles'])" required />
                <x-ui::select name="color" :label="__('ai::tools.logo.colors')" :options="$aiLogo['colors']" :selected="array_key_first($aiLogo['colors'])" required />
            </div>
            <div>
                <x-ui::button type="submit" icon="sparkles" data-ai-logo-generate>{{ __('ai::tools.logo.generate') }}</x-ui::button>
            </div>

            <p class="text-sm text-ink-muted" data-ai-empty>{{ __('ai::tools.logo.empty') }}</p>
            <div class="grid gap-3" hidden data-ai-loading aria-live="polite">
                <p class="text-sm text-ink-muted">{{ __('ai::tools.logo.generating') }}</p>
                <div class="grid grid-cols-2 gap-4"><x-ui::skeleton class="aspect-square" /><x-ui::skeleton class="aspect-square" /><x-ui::skeleton class="aspect-square" /><x-ui::skeleton class="aspect-square" /></div>
            </div>
            <div class="grid gap-4" hidden data-ai-result>
                <div role="radiogroup" aria-label="{{ __('ai::tools.logo.title') }}" class="grid grid-cols-2 gap-4" data-ai-logo-options></div>
                <div class="flex justify-end">
                    <x-ui::button icon="check" disabled data-ai-logo-apply>{{ __('ai::tools.logo.use') }}</x-ui::button>
                </div>
            </div>
            <template data-ai-logo-option>
                <label class="group grid cursor-pointer gap-2">
                    <input type="radio" name="ai_logo_option" class="peer sr-only">
                    <span class="grid aspect-square place-items-center overflow-hidden rounded-card bg-media p-3 ring-2 ring-transparent transition peer-checked:ring-primary peer-focus-visible:ring-focus">
                        <img alt="" class="max-h-full max-w-full object-contain" data-option-image>
                    </span>
                    <span class="flex items-center justify-between gap-2 text-sm font-medium text-ink">
                        <span data-option-label></span>
                        <span class="hidden items-center gap-1 rounded-full bg-primary-subtle px-2 py-0.5 text-xs text-ink-brand group-has-checked:inline-flex"><span class="size-1.5 rounded-full bg-primary"></span>{{ __('ai::tools.logo.selected') }}</span>
                    </span>
                </label>
            </template>
            @include('ai::vendor.product-tools._limit')
            <p class="text-sm text-danger" role="alert" hidden data-ai-error></p>
            <p class="hidden" data-ai-quota></p>
        </form>
    </x-ui::card>
@endif
