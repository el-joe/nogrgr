{{--
    Vendor › Settings › AI translation (VEN-074 / 074a, Figma 180:28706 en · 180:29374 ar ·
    182:29054 mobile, decision #99: per-language model, no words quota).
    Header (Translate a product → products list / VEN-012b tab, Translate full store →
    purchase), 074a result banner, KPI cards, current run with live per-section progress
    (Reverb tenant.<id>.translation-runs + poll), languages card + "Pay & translate"
    (shared language purchase, USD, hosted page or inline Stripe card), run history (Yajra).
--}}
@use('App\Modules\Localization\Enums\TranslationRunStatus')
@use('App\Modules\Localization\Models\Language')
@use('App\Shared\Support\Locale')
@php
    $numberLocale = Locale::numberLocale();
    $format = fn (int $value): string => \Illuminate\Support\Number::format($value, locale: $numberLocale);
    $short = fn (int $value): string => $value >= 1000 ? (string) \Illuminate\Support\Number::abbreviate($value, maxPrecision: 2) : (string) $value;
    $result = session('ai_translation_result') ?? (session('error') ? ['type' => 'error', 'message' => session('error')] : null);
    $prices = $purchasable->mapWithKeys(fn (Language $language) => [$language->id => $language->price()->format()])->all();
    $firstPrice = $purchasable->first()?->price()->format();
    $selectedPrice = $selected?->price()->format();
    $priceIsland = ['prices' => $prices, 'note' => __('ai::ai_translation.purchase.note', ['price' => ':price']), 'pay' => __('ai::ai_translation.purchase.pay', ['price' => ':price'])];
@endphp
<x-vendor::layout :title="__('ai::ai_translation.title')" page="vendor-ai-translation">
    <script type="application/json" data-translation-live>@json($live)</script>
    <script type="application/json" data-language-prices>@json($priceIsland)</script>
    <div class="grid gap-6">
        <x-ui::page-header :title="__('ai::ai_translation.title')" :subtitle="__('ai::ai_translation.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('ai::ai_translation.title') => null]">
            <x-slot:actions>
                @if ($canTranslateProducts)
                    <x-ui::button variant="secondary" :href="vendor_route('catalog.products.index')" class="max-sm:w-full">{{ __('ai::ai_translation.translate_product') }}</x-ui::button>
                @endif
                @if ($canManage && $purchasable->isNotEmpty())
                    <x-ui::button href="#ai-translation-buy" class="max-sm:w-full max-sm:order-first">{{ __('ai::ai_translation.translate_store') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        @if ($result)
            @php $tone = ['success' => 'success', 'info' => 'info', 'error' => 'danger'][$result['type']] ?? 'info'; @endphp
            <div class="flex flex-wrap items-center gap-3 rounded-card bg-surface px-4 py-3 text-sm text-ink" role="status" data-result-banner>
                <x-ui::badge :tone="$tone" dot>{{ __('ai::ai_translation.result.badge.'.$result['type']) }}</x-ui::badge>
                <p class="min-w-0 flex-1">{{ $result['message'] }}</p>
                <button type="button" class="text-ink-secondary hover:text-ink focus-visible:outline-2" data-dismiss-banner>{{ __('ai::ai_translation.dismiss') }}</button>
            </div>
        @endif

        <div class="flex snap-x gap-4 overflow-x-auto pb-1 sm:grid sm:grid-cols-2 sm:overflow-visible xl:grid-cols-4">
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="refresh" :label="__('ai::ai_translation.kpi.runs')" :value="$format($stats['runs'])" :comparison="__('ai::ai_translation.kpi.runs_note', ['month' => $format($stats['this_month']), 'active' => $format($stats['active'])])" />
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="languages" :label="__('ai::ai_translation.kpi.items')" :value="$format($stats['items'])" :comparison="trans_choice('ai::ai_translation.kpi.items_note', $stats['item_languages'], ['count' => $stats['item_languages']])" />
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="sparkles" :label="__('ai::ai_translation.kpi.tokens')" :value="$short($stats['tokens'])" :comparison="__('ai::ai_translation.kpi.tokens_note')" />
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="credit-card" :label="__('ai::ai_translation.kpi.languages')" :value="$format($stats['paid_languages'])" :comparison="$firstPrice ? __('ai::ai_translation.kpi.languages_note', ['price' => $firstPrice]) : __('ai::ai_translation.kpi.languages_note_free')" />
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,364px)]">
            <section class="rounded-card bg-surface p-5 shadow-card" aria-live="polite" data-run-slot>
                @include('ai::vendor.ai-translation._current', ['current' => $current])
            </section>

            <x-ui::card :title="__('ai::ai_translation.languages.title')" :subtitle="__('ai::ai_translation.languages.subtitle')" id="ai-translation-languages">
                @if ($languages->isEmpty())
                    <p class="rounded-control bg-subtle px-3 py-3 text-sm text-ink-secondary">{{ __('ai::ai_translation.languages.empty') }}</p>
                @else
                    <ul class="grid gap-3" role="list">
                        @foreach ($languages as $language)
                            @php
                                $run = $language['run'];
                                $meta = match (true) {
                                    $run === null => __('ai::ai_translation.languages.not_run'),
                                    $run->status === TranslationRunStatus::Queued => __('ai::ai_translation.languages.queued'),
                                    $run->status === TranslationRunStatus::Translating => __('ai::ai_translation.languages.running', ['items' => $format($run->done_items), 'tokens' => $short($run->prompt_tokens + $run->completion_tokens)]),
                                    $run->status === TranslationRunStatus::Failed => __('ai::ai_translation.languages.failed', ['date' => $run->finished_at?->translatedFormat('j M') ?? '—', 'items' => $format($language['items'])]),
                                    default => __('ai::ai_translation.languages.last_run', ['date' => $run->finished_at?->translatedFormat('j M') ?? '—', 'items' => $format($language['items']), 'tokens' => $short($language['tokens'])]),
                                };
                            @endphp
                            <li class="flex items-center gap-3 rounded-control bg-subtle p-3" data-language="{{ $language['code'] }}">
                                <span class="grid size-10 shrink-0 place-items-center rounded-control bg-primary-subtle text-sm font-semibold uppercase text-ink-brand" aria-hidden="true">{{ \Illuminate\Support\Str::limit($language['code'], 2, '') }}</span>
                                <span class="grid min-w-0 flex-1 gap-0.5">
                                    <span class="truncate font-semibold text-ink">{{ $language['name'] }}</span>
                                    <span class="text-xs text-ink-muted">{{ $meta }}</span>
                                </span>
                                @if ($run)
                                    @include('ai::vendor.ai-translation._status', ['status' => $run->status, 'context' => 'language'])
                                @else
                                    <x-ui::badge tone="neutral" dot>{{ __('ai::ai_translation.status.paid') }}</x-ui::badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-6 grid gap-3" id="ai-translation-buy">
                    <h3 class="text-base font-semibold text-ink">{{ __('ai::ai_translation.purchase.title') }}</h3>
                    @if ($purchasable->isEmpty())
                        <p class="text-sm text-ink-secondary">{{ __('ai::ai_translation.purchase.none') }}</p>
                    @elseif (! $canManage)
                        <p class="text-sm text-ink-secondary">{{ __('ai::ai_translation.purchase.no_permission') }}</p>
                    @elseif ($gateways->isEmpty())
                        <p class="text-sm text-ink-secondary">{{ __('ai::ai_translation.purchase.no_gateways') }}</p>
                    @else
                        <form method="POST" action="{{ vendor_route('settings.ai-translation.purchase') }}" class="grid gap-3" data-ai-translation-purchase>
                            @csrf
                            <x-ui::select name="language_id" :label="__('ai::ai_translation.purchase.language')" label-hidden :options="$purchasable->mapWithKeys(fn (Language $language) => [$language->id => $language->native_name && $language->native_name !== $language->name ? $language->name.' · '.$language->native_name : $language->name])->all()" :selected="$selected?->id" :search="$purchasable->count() > 8" data-language-select />
                            <p class="text-xs text-ink-muted" data-price-note><bdi>{{ __('ai::ai_translation.purchase.note', ['price' => $selectedPrice]) }}</bdi></p>
                            @if ($gateways->count() === 1)
                                <input type="hidden" name="gateway" value="{{ $gateways->first()->code->value }}">
                            @else
                                <x-ui::select name="gateway" :label="__('ai::ai_translation.purchase.gateway')" :options="$gateways->mapWithKeys(fn ($gateway) => [$gateway->code->value => $gateway->code->label()])->all()" :selected="$gateways->first()->code->value" :search="false" />
                            @endif
                            <x-ui::stripe-card :gateways="$gateways" :purpose="$purpose" />
                            <x-ui::button type="submit" block data-pay-button>{{ __('ai::ai_translation.purchase.pay', ['price' => $selectedPrice]) }}</x-ui::button>
                        </form>
                    @endif
                </div>
            </x-ui::card>
        </div>

        <section aria-labelledby="ai-translation-history-title">
                <x-ui::datatable
                    id="ai-translation-history"
                    :url="vendor_route('settings.ai-translation.history')"
                    :columns="[
                        ['data' => 'language', 'title' => __('ai::ai_translation.history.language'), 'orderable' => false, 'searchable' => false],
                        ['data' => 'scope', 'title' => __('ai::ai_translation.history.scope'), 'orderable' => false, 'searchable' => false],
                        ['data' => 'status', 'title' => __('ai::ai_translation.history.status'), 'searchable' => false],
                        ['data' => 'done_items', 'title' => __('ai::ai_translation.history.items'), 'align' => 'end', 'searchable' => false],
                        ['data' => 'failed_items', 'title' => __('ai::ai_translation.history.failed'), 'align' => 'end', 'searchable' => false],
                        ['data' => 'tokens', 'title' => __('ai::ai_translation.history.tokens'), 'align' => 'end', 'searchable' => false],
                        ['data' => 'started_at', 'title' => __('ai::ai_translation.history.started'), 'searchable' => false],
                        ['data' => 'finished_at', 'title' => __('ai::ai_translation.history.finished'), 'searchable' => false],
                        ['data' => 'actions', 'title' => __('ai::ai_translation.history.actions'), 'orderable' => false, 'searchable' => false, 'align' => 'end'],
                    ]"
                    :order="[[6, 'desc']]"
                    :empty-title="__('ai::ai_translation.history.empty_title')"
                    :empty-description="__('ai::ai_translation.history.empty_description')"
                >
                    <x-slot:filters>
                        <div class="mb-3 grid gap-1">
                            <h2 id="ai-translation-history-title" class="text-xl font-semibold text-ink sm:text-2xl">{{ __('ai::ai_translation.history.title') }}</h2>
                            <p class="text-sm text-ink-secondary">{{ __('ai::ai_translation.history.subtitle') }}</p>
                        </div>
                        <x-ui::filters-bar target="ai-translation-history" :search-placeholder="__('ai::ai_translation.history.search')">
                            <x-ui::select name="status" :label="__('ai::ai_translation.history.status')" label-hidden :options="collect(TranslationRunStatus::cases())->mapWithKeys(fn ($status) => [$status->value => __('ai::ai_translation.status.'.$status->value)])->all()" :placeholder="__('ai::ai_translation.history.status')" :search="false" allow-clear data-filter :data-filter-label="__('ai::ai_translation.history.status')" class="w-40" />
                            <x-ui::select name="scope" :label="__('ai::ai_translation.history.scope')" label-hidden :options="__('ai::ai_translation.history.scopes')" :placeholder="__('ai::ai_translation.history.scope')" :search="false" allow-clear data-filter :data-filter-label="__('ai::ai_translation.history.scope')" class="w-40" />
                        </x-ui::filters-bar>
                    </x-slot:filters>
                </x-ui::datatable>
        </section>
    </div>
</x-vendor::layout>
