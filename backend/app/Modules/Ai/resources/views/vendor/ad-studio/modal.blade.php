{{--
    VEN-010c AI Advertising Studio — client frame 12:15119 (Tenant Panel Layout): 4-step
    video ad wizard. Header (title, close, product row with picker, stepper "Video type /
    People and voice / Platform and size / Review"), one step panel at a time, Next / Back.
    Generate queues the ad (plan `ai_ads`) and the modal switches to live progress
    (Reverb tenant.<id>.video-ads + poll) → result (player, caption, Download / Copy /
    Share) or failed / limit-reached states. Rendered once per page (dashboard, product
    pages, studio page); openers: [data-ad-studio-open] with optional data-product-id /
    -name / -image. Behaviour: panels/vendor/ad-studio.js.
    Data (view composer): $adStudio {canCreate, config, options, …} | null.
--}}
@php
    $adStudio ??= null;
@endphp
@if ($adStudio !== null && $adStudio['canCreate'])
    @php
        $options = $adStudio['options'];
        $config = $adStudio['config'];
        $sections = [
            1 => ['key' => 'type', 'icon' => 'ad-video'],
            2 => ['key' => 'people', 'icon' => 'ad-user'],
            3 => ['key' => 'platform', 'icon' => 'ad-playlist'],
            4 => ['key' => 'review', 'icon' => 'ad-magic'],
        ];
        $texts = [
            'heading' => __('ai::ad_studio.heading', ['product' => '__PRODUCT__']),
            'headingEmpty' => __('ai::ad_studio.heading_empty'),
            'productRequired' => __('ai::ad_studio.errors.product_required'),
            'generic' => __('ai::ad_studio.errors.generic'),
            'load' => __('ai::ad_studio.errors.load'),
            'copied' => __('ai::ad_studio.result.copied'),
            'linkCopied' => __('ai::ad_studio.result.link_copied'),
            'percent' => __('ai::ad_studio.progress.percent', ['percent' => '__P__']),
            'scene' => __('ai::ad_studio.preview.slide', ['number' => '__N__']),
            'marketAll' => __('ai::ad_studio.market_all'),
        ];
    @endphp
    <script type="application/json" data-ad-studio-config>@json($config + ['texts' => $texts])</script>
    <div id="ad-studio-modal" class="ui-overlay" data-overlay data-sheet data-static role="dialog" aria-modal="true" aria-labelledby="ad-studio-title" aria-describedby="ad-studio-intro" aria-hidden="true" inert data-ad-studio>
        <div class="ui-overlay-scrim"></div>
        <div class="ui-overlay-panel ads-panel max-w-[1027px]">
            <div class="ui-sheet-grabber mx-auto mt-2 h-1 w-10 rounded-full bg-line-strong" data-sheet-handle aria-hidden="true"></div>
            <form class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6" novalidate data-ad-studio-form>
                <div class="grid gap-8 sm:gap-12">
                    <div class="grid gap-6">
                        <div class="flex items-start justify-between gap-4">
                            <h2 id="ad-studio-title" class="ads-title max-sm:text-2xl">{{ __('ai::ad_studio.title') }}</h2>
                            <button type="button" class="ads-close" data-overlay-close aria-label="{{ __('ui.close') }}"><x-ui::icon name="cancel-circle" :size="36" /></button>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="ads-product-image" data-ad-product-image-wrap>
                                <img src="" alt="" class="size-full object-contain" hidden data-ad-product-image>
                                <x-ui::icon name="image" :size="28" class="text-ink-muted" data-ad-product-placeholder />
                            </span>
                            <div class="grid min-w-0 flex-1 gap-1">
                                <p class="ads-heading" data-ad-heading>{{ __('ai::ad_studio.heading_empty') }}</p>
                                <p id="ad-studio-intro" class="text-xs leading-[1.5] text-[var(--vnd-text-sub)]">{{ __('ai::ad_studio.intro') }}</p>
                                <div class="mt-1 max-w-[420px]" hidden data-ad-product-picker>
                                    <x-ui::select name="ad_product" id="ad-studio-product" :label="__('ai::ad_studio.choose_product')" label-hidden :placeholder="__('ai::ad_studio.choose_product')" :ajax-url="$config['urls']['products']" data-ad-product-select />
                                </div>
                                <button type="button" class="justify-self-start text-sm font-medium text-ink-brand hover:underline" hidden data-ad-change-product>{{ __('ai::ad_studio.change_product') }}</button>
                            </div>
                        </div>

                        <ol class="ads-stepper flex-wrap" data-ad-stepper>
                            @foreach ($sections as $number => $section)
                                <li class="ads-step" data-ad-step-indicator="{{ $number }}" data-state="{{ $number === 1 ? 'current' : 'idle' }}" @if ($number === 1) aria-current="step" @endif>
                                    <span class="ads-step-dot">{{ $number }}</span>
                                    <span @class(['max-sm:sr-only' => $number !== 1]) data-ad-step-label>{{ __('ai::ad_studio.steps.'.$section['key']) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Steps 1–4 --}}
                    <div data-ad-steps>
                        @foreach ($sections as $number => $section)
                            <section class="grid gap-4" data-ad-step="{{ $number }}" @if ($number !== 1) hidden @endif aria-labelledby="ad-step-{{ $number }}-title">
                                <div class="flex items-center gap-2">
                                    <span class="ads-section-chip" aria-hidden="true"><x-ui::icon :name="$section['icon']" :size="18" /></span>
                                    <div class="grid min-w-0">
                                        <h3 id="ad-step-{{ $number }}-title" class="ads-section-title">{{ __('ai::ad_studio.sections.'.$section['key'].'.title') }}</h3>
                                        <p class="text-sm leading-[1.5] text-[var(--vnd-text-sub)]">{{ __('ai::ad_studio.sections.'.$section['key'].'.subtitle') }}</p>
                                    </div>
                                </div>

                                @if ($number === 1)
                                    <div class="ads-tiles" role="radiogroup" aria-label="{{ __('ai::ad_studio.fields.type') }}">
                                        @foreach (\App\Modules\Ai\Enums\VideoAdType::cases() as $type)
                                            @include('ai::vendor.ad-studio._tile', ['name' => 'type', 'value' => $type->value, 'label' => $type->label(), 'icon' => $type->icon(), 'checked' => $loop->first])
                                        @endforeach
                                    </div>
                                @elseif ($number === 2)
                                    <fieldset class="grid gap-3">
                                        <legend class="mb-3 text-sm font-medium text-ink">{{ __('ai::ad_studio.fields.presenter') }}</legend>
                                        <div class="ads-tiles">
                                            @foreach (\App\Modules\Ai\Enums\VideoAdPresenter::cases() as $presenter)
                                                @include('ai::vendor.ad-studio._tile', ['name' => 'presenter', 'value' => $presenter->value, 'label' => $presenter->label(), 'icon' => $presenter->icon(), 'checked' => $loop->first])
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <fieldset class="grid gap-3">
                                        <legend class="mb-3 text-sm font-medium text-ink">{{ __('ai::ad_studio.fields.voice') }}</legend>
                                        <div class="ads-tiles">
                                            @foreach ($options['voices'] as $value => $label)
                                                @include('ai::vendor.ad-studio._tile', ['name' => 'voice', 'value' => $value, 'label' => $label, 'compact' => true, 'checked' => $value === 'female'])
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <fieldset class="grid gap-3">
                                        <legend class="mb-3 text-sm font-medium text-ink">{{ __('ai::ad_studio.fields.tone') }}</legend>
                                        <div class="ads-tiles">
                                            @foreach ($options['tones'] as $value => $label)
                                                @include('ai::vendor.ad-studio._tile', ['name' => 'tone', 'value' => $value, 'label' => $label, 'compact' => true, 'checked' => $loop->first])
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <div class="max-w-[440px]">
                                        <x-ui::select name="locale" id="ad-studio-locale" :label="__('ai::ad_studio.fields.language')" :options="$options['languages']" :selected="$config['locale']" :search="false" />
                                    </div>
                                @elseif ($number === 3)
                                    <fieldset class="grid gap-3">
                                        <legend class="mb-3 text-sm font-medium text-ink">{{ __('ai::ad_studio.fields.platform') }}</legend>
                                        <div class="ads-tiles">
                                            @foreach (\App\Modules\Ai\Enums\VideoAdPlatform::cases() as $platform)
                                                @include('ai::vendor.ad-studio._tile', ['name' => 'platform', 'value' => $platform->value, 'label' => $platform->label(), 'icon' => $platform->icon(), 'checked' => $loop->first])
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <fieldset class="grid gap-3">
                                        <legend class="mb-3 text-sm font-medium text-ink">{{ __('ai::ad_studio.fields.size') }}</legend>
                                        <div class="ads-tiles" data-ad-aspects>
                                            @foreach ($options['aspects'] as $value => $label)
                                                @include('ai::vendor.ad-studio._tile', ['name' => 'aspect', 'value' => $value, 'label' => $label, 'compact' => true, 'checked' => $loop->first])
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <fieldset class="grid gap-3">
                                        <legend class="mb-3 text-sm font-medium text-ink">{{ __('ai::ad_studio.fields.duration') }}</legend>
                                        <div class="ads-tiles">
                                            @foreach ($options['durations'] as $value => $label)
                                                @include('ai::vendor.ad-studio._tile', ['name' => 'duration', 'value' => $value, 'label' => $label, 'compact' => true, 'checked' => $loop->first])
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <div class="max-w-[440px]">
                                        <x-ui::select name="country_id" id="ad-studio-country" :label="__('ai::ad_studio.fields.market')" :options="['' => __('ai::ad_studio.market_all')] + $options['countries']" selected="" />
                                    </div>
                                @else
                                    <dl class="grid gap-3 rounded-[16px] bg-[var(--ads-tile)] p-4 sm:grid-cols-2 lg:grid-cols-3" data-ad-summary>
                                        @foreach (['product' => 'product', 'type' => 'type', 'presenter' => 'presenter', 'voice' => 'voice', 'tone' => 'tone', 'locale' => 'language', 'platform' => 'platform', 'aspect' => 'size', 'duration' => 'duration', 'country_id' => 'market'] as $field => $label)
                                            <div class="grid gap-0.5">
                                                <dt class="text-xs text-[var(--vnd-text-sub)]">{{ __('ai::ad_studio.fields.'.$label) }}</dt>
                                                <dd class="text-sm font-medium text-ink" data-ad-summary-value="{{ $field }}">—</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    <x-ui::textarea name="notes" id="ad-studio-notes" :label="__('ai::ad_studio.fields.notes')" :placeholder="__('ai::ad_studio.notes_placeholder')" rows="3" maxlength="300" />
                                    <div class="grid gap-1 text-xs text-[var(--vnd-text-sub)]">
                                        <p>{{ __('ai::ad_studio.uses') }} <span data-ad-quota>{{ $config['quota']['label'] }}</span></p>
                                        <p>{{ $config['renderer'] === 'video' ? __('ai::ad_studio.output_video') : __('ai::ad_studio.output_slideshow') }}</p>
                                    </div>
                                @endif
                            </section>
                        @endforeach

                        {{-- Generating: live progress --}}
                        <section class="grid gap-5" hidden data-ad-progress aria-live="polite">
                            <div class="grid gap-1">
                                <h3 class="ads-section-title">{{ __('ai::ad_studio.progress.title') }}</h3>
                                <p class="text-sm text-[var(--vnd-text-sub)]">{{ __('ai::ad_studio.progress.body') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="ads-progress-track flex-1" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-ad-progressbar><div class="ads-progress-bar" data-ad-progress-bar></div></div>
                                <span class="w-12 text-end text-sm font-semibold text-ink" dir="ltr" data-ad-progress-value>0%</span>
                            </div>
                            <ol class="grid gap-3">
                                @foreach (['scripting', 'visuals', 'voice', 'rendering'] as $stage)
                                    <li class="ads-stage" data-ad-stage="{{ $stage }}" data-state="idle">
                                        <span class="ads-stage-dot" aria-hidden="true"><x-ui::icon name="check" :size="14" /></span>
                                        {{ \App\Modules\Ai\Enums\VideoAdStatus::from($stage)->label() }}
                                    </li>
                                @endforeach
                            </ol>
                        </section>

                        {{-- Ready --}}
                        <section class="grid gap-6 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]" hidden data-ad-result>
                            <div class="ads-player" data-ad-player></div>
                            <div class="grid content-start gap-4">
                                <div class="grid gap-1">
                                    <h3 class="ads-section-title">{{ __('ai::ad_studio.result.title') }}</h3>
                                    <p class="text-lg font-bold text-ink" data-ad-result-headline></p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="ads-hook">{{ __('ai::ad_studio.result.hook') }}</span>
                                    <p class="text-sm text-[var(--vnd-text-sub)]" data-ad-result-hook></p>
                                </div>
                                <div class="grid gap-1">
                                    <p class="text-sm font-medium text-ink">{{ __('ai::ad_studio.result.caption') }}</p>
                                    <p class="whitespace-pre-line rounded-[12px] bg-[var(--vnd-box)] p-3 text-sm text-ink" data-ad-result-text></p>
                                </div>
                                <p class="rounded-[12px] bg-warning-bg p-3 text-xs text-ink-secondary" hidden data-ad-slideshow-note>{{ __('ai::ad_studio.result.slideshow_note') }}</p>
                                <div class="flex flex-wrap gap-3">
                                    <a href="#" class="ads-cta ads-cta-primary" download data-ad-download><x-ui::icon name="download" :size="20" />{{ __('ai::ad_studio.result.download') }}</a>
                                    <button type="button" class="ads-cta ads-cta-outline" data-ad-copy><x-ui::icon name="copy" :size="20" />{{ __('ai::ad_studio.result.copy') }}</button>
                                </div>
                                <div class="flex flex-wrap items-center gap-2" data-ad-share>
                                    <span class="text-sm font-medium text-ink">{{ __('ai::ad_studio.result.share') }}</span>
                                    <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm" data-ad-share-native hidden><x-ui::icon name="link" :size="16" />{{ __('ai::ad_studio.result.share') }}</button>
                                    <a href="#" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-[var(--vnd-box)] text-ink hover:text-primary" data-ad-share-link="whatsapp" aria-label="{{ __('ai::ad_studio.result.share_whatsapp') }}"><x-ui::icon name="social-whatsapp" :size="20" /></a>
                                    <a href="#" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-[var(--vnd-box)] text-ink hover:text-primary" data-ad-share-link="x" aria-label="{{ __('ai::ad_studio.result.share_x') }}"><x-ui::icon name="social-x" :size="20" /></a>
                                    <a href="#" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-[var(--vnd-box)] text-ink hover:text-primary" data-ad-share-link="facebook" aria-label="{{ __('ai::ad_studio.result.share_facebook') }}"><x-ui::icon name="social-facebook" :size="20" /></a>
                                    <button type="button" class="grid size-10 place-items-center rounded-full bg-[var(--vnd-box)] text-ink hover:text-primary" data-ad-copy-link aria-label="{{ __('ai::ad_studio.result.copy_link') }}"><x-ui::icon name="link" :size="20" /></button>
                                </div>
                            </div>
                        </section>

                        {{-- Failed / limit reached --}}
                        <section class="grid gap-3 rounded-[16px] bg-danger-bg p-5" role="alert" hidden data-ad-failed>
                            <p class="text-base font-semibold text-ink">{{ __('ai::ad_studio.failed.title') }}</p>
                            <p class="text-sm text-ink-secondary" data-ad-failed-text></p>
                        </section>
                        <section class="grid gap-2 rounded-[16px] bg-warning-bg p-5" role="alert" hidden data-ad-limit>
                            <p class="text-base font-semibold text-ink">{{ __('ai::ad_studio.limit.title') }}</p>
                            <p class="text-sm text-ink-secondary" data-ad-limit-text></p>
                            @if (Route::has('vendor.finance.subscription') && auth('vendor')->user()?->can('finance.subscription.view'))
                                <a href="{{ vendor_route('finance.subscription') }}" class="text-sm font-medium text-ink-brand hover:underline">{{ __('ai::ad_studio.limit.upgrade') }}</a>
                            @endif
                        </section>
                    </div>

                    <p class="text-sm text-danger" role="alert" hidden data-ad-error></p>

                    <div class="flex flex-wrap gap-3 max-sm:flex-col-reverse" data-ad-footer>
                        <button type="button" class="ads-cta ads-cta-outline max-sm:w-full" hidden data-ad-back>{{ __('ai::ad_studio.back') }}</button>
                        <button type="button" class="ads-cta ads-cta-primary max-sm:w-full" data-ad-next>{{ __('ai::ad_studio.next') }}</button>
                        <button type="submit" class="ads-cta ads-cta-primary max-sm:w-full" hidden data-ad-generate><x-ui::icon name="sparkles" :size="20" />{{ __('ai::ad_studio.generate') }}</button>
                        <button type="button" class="ads-cta ads-cta-primary max-sm:w-full" hidden data-ad-retry><x-ui::icon name="refresh" :size="20" />{{ __('ai::ad_studio.failed.retry') }}</button>
                        <button type="button" class="ads-cta ads-cta-outline max-sm:w-full" hidden data-ad-another>{{ __('ai::ad_studio.result.another') }}</button>
                        <a href="{{ $config['urls']['library'] }}" class="ads-cta ads-cta-outline max-sm:w-full" hidden data-ad-library>{{ __('ai::ad_studio.result.library') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif
