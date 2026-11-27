{{--
    Vendor › Custom theme › Developer guide (VEN-036 docs, linked from the Help center):
    the guide text (content::custom_theme_docs, en / ar), then the generated variable
    reference per template (ThemeVariableReference — the same data the presenters send),
    the sandbox whitelist (ThemeSecurityPolicy), url() page names and the limits.
--}}
@php
    $docs = trans('content::custom_theme_docs');
    $sections = is_array($docs['sections'] ?? null) ? $docs['sections'] : [];
    $faq = is_array($docs['faq'] ?? null) ? $docs['faq'] : [];
    $table = function (array $rows): string {
        return view('storefront::vendor.custom-themes._variables', ['rows' => $rows])->render();
    };
@endphp
<x-vendor::layout :title="__('content::custom_themes.docs_link')" page="vendor-custom-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="(string) ($docs['title'] ?? __('content::custom_themes.docs_link'))"
            :subtitle="(string) ($docs['intro'] ?? '')"
            :breadcrumbs="[__('content::custom_themes.crumb') => null, __('content::custom_themes.title') => vendor_route('store.custom-themes.index'), __('content::custom_themes.docs_link') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="vendor_route('store.custom-themes.starter-kit')">{{ __('content::custom_themes.starter_kit') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[240px_minmax(0,1fr)]">
            <nav aria-label="{{ __('content::custom_themes.docs.contents') }}" class="hidden xl:block">
                <x-ui::card :title="__('content::custom_themes.docs.contents')">
                    <ol class="grid gap-2 text-sm">
                        @foreach ($sections as $key => $section)
                            <li><a href="#doc-{{ $key }}" class="text-ink-secondary hover:text-primary">{{ $section['title'] ?? $key }}</a></li>
                        @endforeach
                        <li><a href="#doc-variables" class="text-ink-secondary hover:text-primary">{{ __('content::custom_themes.docs.variables') }}</a></li>
                        <li><a href="#doc-policy" class="text-ink-secondary hover:text-primary">{{ __('content::custom_themes.docs.policy') }}</a></li>
                        <li><a href="#doc-limits" class="text-ink-secondary hover:text-primary">{{ __('content::custom_themes.docs.limits') }}</a></li>
                        <li><a href="#doc-faq" class="text-ink-secondary hover:text-primary">{{ __('content::custom_themes.docs.faq') }}</a></li>
                    </ol>
                </x-ui::card>
            </nav>

            <div class="grid min-w-0 gap-6">
                @foreach ($sections as $key => $section)
                    <x-ui::card :title="(string) ($section['title'] ?? $key)" :id="'doc-'.$key">
                        <div class="grid gap-3 text-sm leading-6 text-ink">
                            @foreach ((array) ($section['body'] ?? []) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                            @if (! empty($section['steps']))
                                <ol class="ms-5 grid list-decimal gap-1">
                                    @foreach ((array) $section['steps'] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ol>
                            @endif
                            @if (! empty($section['code']))
                                <pre class="overflow-x-auto rounded-card bg-neutral-900 p-4 text-[13px] leading-5 text-neutral-100" dir="ltr"><code>{{ $section['code'] }}</code></pre>
                            @endif
                        </div>
                    </x-ui::card>
                @endforeach

                <x-ui::card :title="__('content::custom_themes.docs.variables')" id="doc-variables">
                    <div class="grid gap-6">
                        <p class="text-sm text-ink-secondary">{{ __('content::custom_themes.docs.variables_intro') }}</p>
                        <section class="grid gap-2">
                            <h3 class="text-base font-semibold text-ink">{{ __('content::custom_themes.docs.globals') }}</h3>
                            {!! $table($globals) !!}
                        </section>
                        @foreach ($pages as $page)
                            <section class="grid gap-2" id="doc-vars-{{ $page['template']->value }}">
                                <h3 class="text-base font-semibold text-ink"><span dir="ltr" class="break-all font-mono">{{ $page['template']->file() }}</span> · {{ $page['template']->label() }}</h3>
                                {!! $table($page['rows']) !!}
                            </section>
                        @endforeach
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('content::custom_themes.docs.policy')" id="doc-policy">
                    <dl class="grid gap-4 text-sm">
                        @foreach (['tags', 'filters', 'functions', 'tests'] as $group)
                            <div class="grid gap-1">
                                <dt class="font-semibold text-ink">{{ __('content::custom_themes.docs.'.$group) }}</dt>
                                <dd class="flex flex-wrap gap-1.5" dir="ltr">
                                    @foreach ($policy[$group] as $name)
                                        <code class="rounded bg-subtle px-1.5 py-0.5 text-[13px] text-ink">{{ $name }}</code>
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                        <div class="grid gap-1">
                            <dt class="font-semibold text-ink">{{ __('content::custom_themes.docs.url_names') }}</dt>
                            <dd class="flex flex-wrap gap-1.5" dir="ltr">
                                @foreach ($urls as $name)
                                    <code class="rounded bg-subtle px-1.5 py-0.5 text-[13px] text-ink">{{ $name }}</code>
                                @endforeach
                            </dd>
                        </div>
                    </dl>
                </x-ui::card>

                <x-ui::card :title="__('content::custom_themes.docs.limits')" :padding="false" id="doc-limits">
                    <div class="relative overflow-x-auto">
                        <table class="vnd-table w-full text-sm">
                            <thead>
                                <tr class="text-start text-sm text-ink-secondary">
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.docs.limit') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.docs.value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($limits as $key => $value)
                                    <tr><td class="px-5 py-2 text-ink">{{ __('content::custom_themes.docs.limit_names.'.$key) }}</td><td class="px-5 py-2 font-medium text-ink">{{ number_format($value) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui::card>

                @if ($faq !== [])
                    <x-ui::card :title="__('content::custom_themes.docs.faq')" id="doc-faq">
                        <dl class="grid gap-4 text-sm">
                            @foreach ($faq as $item)
                                <div class="grid gap-1">
                                    <dt class="font-semibold text-ink">{{ $item['q'] ?? '' }}</dt>
                                    <dd class="leading-6 text-ink-secondary">{{ $item['a'] ?? '' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-ui::card>
                @endif
            </div>
        </div>
    </div>
</x-vendor::layout>
