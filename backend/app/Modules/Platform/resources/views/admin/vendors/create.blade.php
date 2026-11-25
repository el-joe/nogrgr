{{--
    Admin › Vendors › Create vendor (ADM-040a, Figma 72:48932): 4-step wizard
    (Owner, Store, Plan, Review) in one form with a single "Create & provision" submit.
    Steps, the live sub-domain check, the owner summary and the review are driven by
    resources/js/panels/admin/vendors.js; a 422 jumps back to the step with the error.
--}}
@php
    $steps = ['owner', 'store', 'plan', 'review'];
    $assignmentHints = __('platform::vendors.create.assignment_hints');
@endphp
<x-layouts.panel surface="admin" :title="__('platform::vendors.create.title')" data-page="admin-vendors">
    <form
        id="vendor-create-form"
        method="POST"
        action="{{ route('admin.vendors.store') }}"
        data-ajax
        data-vendor-wizard
        data-slug-url="{{ route('admin.vendors.slug-check') }}"
        data-base-domain="{{ $baseDomain }}"
        class="grid gap-6"
        novalidate
    >
        @csrf

        <x-ui::page-header
            :title="__('platform::vendors.create.title')"
            :subtitle="__('platform::vendors.create.subtitle')"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::vendors.create.title') => null]"
        >
            <x-slot:actions>
                <x-ui::button type="submit" icon="check">{{ __('platform::vendors.create.submit') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <ol class="flex items-center gap-3 overflow-x-auto" aria-label="{{ __('platform::vendors.create.steps_label') }}">
            @foreach ($steps as $index => $step)
                <li @class(['flex items-center gap-3', 'flex-1' => ! $loop->last])>
                    <button
                        type="button"
                        class="group flex shrink-0 items-center gap-2.5 rounded-chip text-sm font-medium text-ink-secondary data-[state=current]:text-ink data-[state=done]:text-ink"
                        data-wizard-goto="{{ $index }}"
                        data-state="{{ $index === 0 ? 'current' : 'upcoming' }}"
                        @if ($index === 0) aria-current="step" @endif
                    >
                        <span class="grid size-7 place-items-center rounded-full border border-line-strong text-xs text-ink-secondary group-data-[state=current]:border-primary group-data-[state=current]:bg-primary group-data-[state=current]:text-on-primary group-data-[state=done]:border-success group-data-[state=done]:bg-success group-data-[state=done]:text-white">
                            <span class="group-data-[state=done]:hidden">{{ \Illuminate\Support\Number::format($index + 1, locale: \App\Shared\Support\Locale::numberLocale()) }}</span>
                            <x-ui::icon name="check" :size="14" class="hidden group-data-[state=done]:block" />
                        </span>
                        {{ __('platform::vendors.create.steps.'.$step) }}
                    </button>
                    @unless ($loop->last)
                        <span class="h-0.5 min-w-8 flex-1 rounded-full bg-line" data-wizard-line="{{ $index }}" aria-hidden="true"></span>
                    @endunless
                </li>
            @endforeach
        </ol>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
            <x-ui::card>
                <p class="mb-1 text-xs font-medium text-ink-muted" data-wizard-counter data-template="{{ __('platform::vendors.create.step_of', ['current' => ':current', 'total' => count($steps)]) }}">{{ __('platform::vendors.create.step_of', ['current' => 1, 'total' => count($steps)]) }}</p>

                {{-- Step 1: owner --}}
                <section data-wizard-step="owner" class="grid gap-5" aria-labelledby="step-owner-title">
                    <h2 id="step-owner-title" class="text-base font-semibold text-ink">{{ __('platform::vendors.create.steps.owner') }}</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui::input name="owner_name" :label="__('platform::vendors.fields.owner_name')" required maxlength="120" autocomplete="off" data-summary="owner_name" />
                        <x-ui::input name="owner_email" type="email" :label="__('platform::vendors.fields.owner_email')" required maxlength="191" autocomplete="off" dir="ltr" data-summary="owner_email" />
                        <x-ui::phone name="owner_phone" :label="__('platform::vendors.fields.owner_phone')" data-summary="owner_phone" />
                        <x-ui::input name="owner_password" type="password" :label="__('platform::vendors.fields.owner_password')" :helper="__('platform::vendors.fields.owner_password_hint')" required minlength="8" maxlength="72" autocomplete="new-password" />
                    </div>
                </section>

                {{-- Step 2: store --}}
                <section data-wizard-step="store" class="grid gap-5" aria-labelledby="step-store-title" hidden>
                    <h2 id="step-store-title" class="text-base font-semibold text-ink">{{ __('platform::vendors.create.steps.store') }}</h2>
                    <x-ui::input name="store_name" :label="__('platform::vendors.fields.store_name')" required maxlength="120" data-summary="store_name" />
                    <div class="grid gap-1.5">
                        <x-ui::input name="slug" :label="__('platform::vendors.fields.slug')" :helper="__('platform::vendors.fields.slug_hint')" required maxlength="63" pattern="[a-z0-9-]+" autocomplete="off" spellcheck="false" dir="ltr" data-slug-input data-summary="slug">
                            <x-slot:suffix>
                                <span class="shrink-0 text-sm text-ink-muted">.{{ $baseDomain }}</span>
                            </x-slot:suffix>
                        </x-ui::input>
                        <p class="text-xs" data-slug-status aria-live="polite" hidden
                            data-checking="{{ __('platform::vendors.create.slug_checking') }}"
                            data-failed="{{ __('platform::vendors.create.slug_error') }}"></p>
                    </div>
                    <x-ui::input name="custom_domain" :label="__('platform::vendors.fields.custom_domain')" :helper="__('platform::vendors.fields.custom_domain_hint')" maxlength="253" autocomplete="off" spellcheck="false" dir="ltr" :placeholder="__('platform::vendors.domains.host_placeholder')" data-summary="custom_domain" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui::select name="default_country_id" :label="__('platform::vendors.fields.default_country')" :options="$countries" :placeholder="__('platform::vendors.fields.select_country')" allow-clear data-summary="default_country_id" />
                        <x-ui::input name="profit_percent" type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('platform::vendors.fields.profit_percent')" value="70" required dir="ltr" data-summary="profit_percent">
                            <x-slot:suffix><span class="text-sm text-ink-muted">%</span></x-slot:suffix>
                        </x-ui::input>
                    </div>
                    <x-ui::select name="selling_country_ids" multiple :label="__('platform::vendors.fields.countries')" :helper="__('platform::vendors.fields.countries_hint')" :options="$countries" :placeholder="__('platform::vendors.fields.select_countries')" data-summary="selling_country_ids" />
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-ink">{{ __('platform::vendors.fields.languages') }}</span>
                        <div class="flex flex-wrap gap-2">
                            <x-ui::badge tone="brand">{{ __('ui.languages.ar') }}</x-ui::badge>
                            <x-ui::badge tone="brand">{{ __('ui.languages.en') }}</x-ui::badge>
                        </div>
                        <p class="text-xs text-ink-secondary">{{ __('platform::vendors.create.languages_fixed') }}</p>
                    </div>
                </section>

                {{-- Step 3: plan --}}
                <section data-wizard-step="plan" class="grid gap-5" aria-labelledby="step-plan-title" hidden>
                    <h2 id="step-plan-title" class="text-base font-semibold text-ink">{{ __('platform::vendors.create.steps.plan') }}</h2>
                    <x-ui::select name="plan_id" :label="__('platform::vendors.fields.plan')" :helper="__('platform::vendors.fields.plan_hint')" :options="$plans" :placeholder="__('platform::vendors.fields.select_plan')" allow-clear data-summary="plan_id" />
                    <x-ui::radio-group name="term" :label="__('platform::vendors.fields.term')" :options="$terms" value="monthly" data-summary="term" />
                    <x-ui::radio-group name="plan_assignment" :label="__('platform::vendors.fields.plan_assignment')" :options="$assignments" value="trial" :inline="false" data-summary="plan_assignment" />
                    <ul class="grid gap-1 rounded-control bg-subtle p-3 text-xs text-ink-secondary">
                        @foreach ($assignments as $value => $label)
                            <li><span class="font-medium text-ink">{{ $label }}:</span> {{ $assignmentHints[$value] ?? '' }}</li>
                        @endforeach
                    </ul>
                </section>

                {{-- Step 4: review --}}
                <section data-wizard-step="review" class="grid gap-5" aria-labelledby="step-review-title" hidden>
                    <div>
                        <h2 id="step-review-title" class="text-base font-semibold text-ink">{{ __('platform::vendors.create.steps.review') }}</h2>
                        <p class="text-sm text-ink-secondary">{{ __('platform::vendors.create.review_hint') }}</p>
                    </div>
                    <dl class="grid gap-3 text-sm" data-review data-empty="{{ __('platform::vendors.create.review_none') }}">
                        @foreach ([
                            'owner_name' => __('platform::vendors.fields.owner_name'),
                            'owner_email' => __('platform::vendors.fields.owner_email'),
                            'owner_phone' => __('platform::vendors.fields.owner_phone'),
                            'store_name' => __('platform::vendors.fields.store_name'),
                            'slug' => __('platform::vendors.fields.slug'),
                            'custom_domain' => __('platform::vendors.fields.custom_domain'),
                            'default_country_id' => __('platform::vendors.fields.default_country'),
                            'selling_country_ids' => __('platform::vendors.fields.countries'),
                            'profit_percent' => __('platform::vendors.fields.profit_percent'),
                            'plan_id' => __('platform::vendors.fields.plan'),
                            'term' => __('platform::vendors.fields.term'),
                            'plan_assignment' => __('platform::vendors.fields.plan_assignment'),
                        ] as $key => $label)
                            <div class="flex items-start justify-between gap-4 border-b border-line pb-3 last:border-0 last:pb-0">
                                <dt class="text-ink-secondary">{{ $label }}</dt>
                                <dd class="text-end font-medium text-ink" data-review-value="{{ $key }}">{{ __('platform::vendors.create.review_none') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <div class="mt-6 flex items-center justify-between gap-3 border-t border-line pt-5">
                    <x-ui::button variant="secondary" icon="chevron-left" class="rtl:[&_svg]:rotate-180" data-wizard-prev hidden>{{ __('platform::vendors.create.back') }}</x-ui::button>
                    <span></span>
                    <x-ui::button icon-trailing="chevron-right" class="rtl:[&_svg]:rotate-180" data-wizard-next>{{ __('platform::vendors.create.next') }}</x-ui::button>
                    <x-ui::button type="submit" icon="check" data-wizard-submit hidden>{{ __('platform::vendors.create.submit') }}</x-ui::button>
                </div>
            </x-ui::card>

            <aside class="grid gap-4">
                <x-ui::card :title="__('platform::vendors.create.owner_card')">
                    <dl class="grid gap-3 text-sm" data-owner-summary data-empty="{{ __('platform::vendors.create.not_set') }}">
                        @foreach (['owner_name' => 'owner_name', 'owner_email' => 'owner_email', 'owner_phone' => 'owner_phone'] as $key => $field)
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-ink-secondary">{{ __('platform::vendors.fields.'.$field) }}</dt>
                                <dd class="min-w-0 truncate text-end font-medium text-ink" @if ($key !== 'owner_name') dir="ltr" @endif data-owner-value="{{ $key }}">{{ __('platform::vendors.create.not_set') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui::card>
                <div class="flex gap-3 rounded-card border border-info/20 bg-info-bg p-4 text-sm text-ink" role="note">
                    <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
                    <p>{{ __('platform::vendors.create.info') }}</p>
                </div>
            </aside>
        </div>
    </form>
</x-layouts.panel>
