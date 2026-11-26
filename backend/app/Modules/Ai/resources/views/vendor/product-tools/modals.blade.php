{{--
    VEN-010b AI price finder (206:39026 "AI price finder") and VEN-010c AI social posts
    (206:39026 "AI social posts") modals of a product page. Both call OpenAI only through
    their endpoint (metered by the plan's monthly AI calls); panels/vendor/ai-tools.js
    renders the results and the states: idle, loading, result, empty, limit reached (429),
    provider error. The price is a suggestion only: "Use" fills the price field of the
    own-product form (or copies the price) — nothing is saved by the tool.
    Data (view composer): $product, $aiTools {countries, languages, platforms, quota, urls}.
--}}
@php($aiTools ??= null)
@if ($aiTools !== null)
    @php($quotaText = $aiTools['quota']['label'])

    {{-- VEN-010b AI price finder --}}
    <x-ui::modal id="product-ai-price-modal" :title="__('ai::tools.price_finder.title')" :subtitle="__('ai::tools.price_finder.subtitle')" size="md">
        <div class="grid gap-5" data-ai-price data-url="{{ $aiTools['urls']['price'] }}"
            data-text-use="{{ __('ai::tools.price_finder.use', ['price' => '__PRICE__']) }}"
            data-text-copied="{{ __('ai::tools.price_finder.copied', ['price' => '__PRICE__']) }}"
            data-text-generic="{{ __('ai::tools.errors.generic') }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[200px] flex-1">
                    <x-ui::select name="ai_price_country" :label="__('ai::tools.price_finder.country')" :options="$aiTools['countries']" :selected="array_key_first($aiTools['countries'])" data-ai-price-country />
                </div>
                <x-ui::button icon="search" data-ai-price-search>{{ __('ai::tools.price_finder.search') }}</x-ui::button>
            </div>

            <div class="grid gap-2" hidden data-ai-loading aria-live="polite">
                <p class="text-sm text-ink-muted">{{ __('ai::tools.price_finder.searching') }}</p>
                <x-ui::skeleton class="h-16" /><x-ui::skeleton class="h-10" /><x-ui::skeleton class="h-10" />
            </div>

            <div class="grid gap-4" hidden data-ai-result>
                <dl class="grid grid-cols-3 gap-3">
                    <div><dt class="text-xs text-ink-muted">{{ __('ai::tools.price_finder.average') }}</dt><dd class="text-xl font-bold text-ink" dir="ltr" data-ai-stat="average"></dd></div>
                    <div><dt class="text-xs text-ink-muted">{{ __('ai::tools.price_finder.median') }}</dt><dd class="text-xl font-bold text-ink" dir="ltr" data-ai-stat="median"></dd></div>
                    <div><dt class="text-xs text-ink-muted">{{ __('ai::tools.price_finder.range') }}</dt><dd class="text-xl font-bold text-ink" dir="ltr" data-ai-stat="range"></dd></div>
                </dl>
                <div>
                    <p class="mb-2 text-sm font-medium text-ink">{{ __('ai::tools.price_finder.hits') }}</p>
                    <ul class="divide-y divide-line" data-ai-hits></ul>
                </div>
                <p class="rounded-control bg-subtle px-3 py-2 text-xs text-ink-secondary" data-ai-suggestion data-template="{{ __('ai::tools.price_finder.suggestion', ['price' => '__PRICE__']) }}"></p>
            </div>
            <template data-ai-hit>
                <li class="flex items-center gap-3 py-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="tag" :size="16" /></span>
                    <a class="min-w-0 flex-1 hover:underline" target="_blank" rel="noopener noreferrer nofollow" data-hit-link>
                        <span class="block truncate text-sm font-medium text-ink" data-hit-title></span>
                        <span class="block text-xs text-ink-muted" dir="ltr" data-hit-price></span>
                    </a>
                </li>
            </template>

            <p class="text-sm text-ink-muted" hidden data-ai-empty>{{ __('ai::tools.price_finder.empty') }}</p>
            @include('ai::vendor.product-tools._limit')
            <p class="text-sm text-danger" role="alert" hidden data-ai-error></p>
            <p class="text-xs text-ink-muted" data-ai-quota>{{ $quotaText }}</p>
        </div>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ai::tools.price_finder.close') }}</x-ui::button>
            <x-ui::button icon="check" hidden data-ai-price-use></x-ui::button>
        </x-slot:footer>
    </x-ui::modal>

    {{-- VEN-010c AI social posts --}}
    <x-ui::modal id="product-ai-social-modal" :title="__('ai::tools.social.title')" :subtitle="__('ai::tools.social.subtitle')" size="md">
        <div class="grid gap-5" data-ai-social data-url="{{ $aiTools['urls']['social'] }}"
            data-text-copied="{{ __('ai::tools.social.copied') }}"
            data-text-generic="{{ __('ai::tools.errors.generic') }}">
            <div role="radiogroup" aria-label="{{ __('ai::tools.social.platform') }}" class="flex flex-wrap gap-2">
                @foreach ($aiTools['platforms'] as $value => $label)
                    <label class="inline-flex h-9 cursor-pointer items-center rounded-full bg-subtle px-4 text-sm font-medium text-ink transition-colors focus-within:ring-2 focus-within:ring-focus has-checked:bg-inverse has-checked:text-on-inverse">
                        <input type="radio" name="ai_social_platform" value="{{ $value }}" class="sr-only" @checked($loop->first) data-ai-social-platform>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-ui::select name="ai_social_locale" :label="__('ai::tools.social.language')" :options="$aiTools['languages']" :selected="app()->getLocale()" data-ai-social-locale />

            <p class="text-sm text-ink-muted" data-ai-empty>{{ __('ai::tools.social.empty') }}</p>
            <div class="grid gap-2" hidden data-ai-loading aria-live="polite">
                <p class="text-sm text-ink-muted">{{ __('ai::tools.social.generating') }}</p>
                <x-ui::skeleton class="h-28" />
            </div>
            <div class="grid gap-4" hidden data-ai-result>
                <x-ui::textarea name="ai_social_text" :label="__('ai::tools.social.caption')" :helper="__('ai::tools.social.caption_hint')" :rows="6" maxlength="3000" data-ai-social-text />
                <div>
                    <p class="mb-2 text-sm font-medium text-ink">{{ __('ai::tools.social.image') }}</p>
                    <div class="flex items-center gap-4" data-ai-social-media>
                        <img alt="" class="size-28 rounded-card bg-subtle object-contain" data-ai-social-image>
                        <a class="vnd-cta vnd-cta-outline px-4" download data-ai-social-download><x-ui::icon name="download" :size="18" />{{ __('ai::tools.social.download') }}</a>
                    </div>
                    <p class="text-sm text-ink-muted" hidden data-ai-social-no-image>{{ __('ai::tools.social.no_image') }}</p>
                </div>
            </div>
            @include('ai::vendor.product-tools._limit')
            <p class="text-sm text-danger" role="alert" hidden data-ai-error></p>
            <p class="text-xs text-ink-muted" data-ai-quota>{{ $quotaText }}</p>
        </div>
        <x-slot:footer>
            <x-ui::button variant="secondary" icon="refresh" hidden data-ai-social-generate data-label-again="{{ __('ai::tools.social.regenerate') }}">{{ __('ai::tools.social.generate') }}</x-ui::button>
            <x-ui::button icon="copy" hidden data-ai-social-copy>{{ __('ai::tools.social.copy') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif

{{-- VEN-010c AI Advertising Studio (video ad wizard) for this product. --}}
@include('ai::vendor.ad-studio.modal', ['product' => $product ?? null])
