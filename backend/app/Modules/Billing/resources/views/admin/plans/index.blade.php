{{--
    Admin › Vendors › Plans (ADM-046, Figma 73:57264): plan cards with live store counts,
    the plan comparison matrix (limits + features), and the server-side plans table
    with edit / delete (a plan with open subscriptions cannot be deleted).
--}}
@use('App\Modules\Billing\Enums\BillingTerm')
@use('App\Modules\Billing\Enums\PlanLimit')
@php
    $canManage = (bool) auth('admin')->user()?->can('plans.manage');
    $featured = $plans->sortByDesc('open_subscriptions_count')->first();
    $bands = ['bg-linear-to-br from-primary-subtle to-primary/30', 'bg-linear-to-br from-primary/70 to-primary', 'bg-linear-to-br from-[var(--nog-highlight-from)] to-[var(--nog-highlight-to)]'];
    $limitValue = fn ($plan, PlanLimit $limit) => $plan->limit($limit) === null
        ? __('billing::plans.unlimited')
        : \Illuminate\Support\Number::format($plan->limit($limit), locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<x-layouts.panel surface="admin" :title="__('billing::plans.title')">
    <x-ui::page-header
        :title="__('billing::plans.title')"
        :subtitle="__('billing::plans.subtitle')"
        :breadcrumbs="[__('navigation.sections.vendors') => null, __('billing::plans.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.plans.create')">{{ __('billing::plans.new') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    @if ($plans->isEmpty())
        <x-ui::card>
            <x-ui::empty-state :title="__('billing::plans.empty_title')" :description="__('billing::plans.empty_description')" icon="star">
                @if ($canManage)
                    <x-ui::button icon="plus" :href="route('admin.plans.create')">{{ __('billing::plans.new') }}</x-ui::button>
                @endif
            </x-ui::empty-state>
        </x-ui::card>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('billing::plans.cards_label') }}">
            @foreach ($plans as $plan)
                @php $isFeatured = $featured?->is($plan) && ($plan->open_subscriptions_count ?? 0) > 0; @endphp
                <article @class(['overflow-hidden rounded-card border bg-surface shadow-card', 'border-primary ring-1 ring-primary' => $isFeatured, 'border-line' => ! $isFeatured])>
                    <div @class(['relative flex h-[68px] items-end p-3', $bands[$loop->index % count($bands)]]) aria-hidden="true">
                        <span class="grid size-9 place-items-center rounded-control bg-surface/90 text-ink-brand shadow-card">
                            <x-ui::icon :name="$plan->icon ?: 'star'" :size="18" />
                        </span>
                    </div>
                    <div class="grid gap-1.5 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="min-w-0 truncate text-sm font-medium text-ink">
                                {{ $plan->name }} · <bdi dir="ltr">{{ $plan->priceFor(BillingTerm::Monthly)->format() }}</bdi> {{ __('billing::plans.per_month') }}
                            </h2>
                            <x-ui::badge :tone="$plan->status === \App\Modules\Billing\Enums\PlanStatus::Active ? 'success' : 'neutral'">
                                {{ trans_choice('billing::plans.stores_count', $plan->open_subscriptions_count ?? 0, ['count' => $plan->open_subscriptions_count ?? 0]) }}
                            </x-ui::badge>
                        </div>
                        <p class="text-xs leading-4 text-ink-secondary">
                            <bdi dir="ltr">{{ $plan->priceFor(BillingTerm::Yearly)->format() }}</bdi> {{ __('billing::plans.per_year') }}
                            @if ($plan->trial_days > 0) · {{ trans_choice('billing::plans.trial_days_value', $plan->trial_days, ['count' => $plan->trial_days]) }} @endif
                            · {{ __('billing::plans.card_products', ['value' => $limitValue($plan, PlanLimit::Products)]) }}
                            · {{ __('billing::plans.card_ai_calls', ['value' => $limitValue($plan, PlanLimit::AiCalls)]) }}
                        </p>
                        @if ($canManage)
                            <div class="flex justify-end">
                                <x-ui::button variant="ghost" size="sm" icon="edit" :href="route('admin.plans.edit', $plan)">{{ __('billing::plans.edit') }}</x-ui::button>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <x-ui::card :title="__('billing::plans.comparison')" :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-subtle text-start text-xs text-ink-secondary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('billing::plans.feature') }}</th>
                            @foreach ($plans as $plan)
                                <th scope="col" class="px-4 py-3 text-start font-medium">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($limits as $limit)
                            <tr class="border-t border-line">
                                <th scope="row" class="px-4 py-3 text-start font-normal text-ink">{{ $limit->label() }}</th>
                                @foreach ($plans as $plan)
                                    <td class="px-4 py-3 text-ink">{{ $limitValue($plan, $limit) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        @foreach ($features as $feature)
                            <tr class="border-t border-line">
                                <th scope="row" class="px-4 py-3 text-start font-normal text-ink">{{ $feature->label() }}</th>
                                @foreach ($plans as $plan)
                                    <td class="px-4 py-3 text-ink">{{ $plan->hasFeature($feature) ? __('billing::plans.yes') : '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui::card>
    @endif

    <section class="grid gap-3" aria-labelledby="plans-table-title">
        <h2 id="plans-table-title" class="text-base font-semibold text-ink">{{ __('billing::plans.all_plans') }}</h2>
        <x-ui::datatable
            id="plans"
            :url="route('admin.plans.data')"
            :columns="[
                ['data' => 'name', 'title' => __('billing::plans.columns.plan'), 'searchable' => false],
                ['data' => 'monthly_price_minor', 'title' => __('billing::plans.fields.monthly_price'), 'align' => 'end', 'searchable' => false],
                ['data' => 'yearly_price_minor', 'title' => __('billing::plans.fields.yearly_price'), 'align' => 'end', 'searchable' => false],
                ['data' => 'trial_days', 'title' => __('billing::plans.fields.trial_days'), 'searchable' => false],
                ['data' => 'open_subscriptions_count', 'title' => __('billing::plans.columns.stores'), 'align' => 'end', 'searchable' => false],
                ['data' => 'status', 'title' => __('billing::plans.fields.status'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[1, 'asc']]"
            :empty-title="__('billing::plans.empty_title')"
            :empty-description="__('billing::plans.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="plans" :search-placeholder="__('billing::plans.search')">
                    <x-ui::select name="status" :label="__('billing::plans.fields.status')" label-hidden :options="$statuses" :placeholder="__('billing::plans.fields.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::plans.fields.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="route('admin.plans.create')">{{ __('billing::plans.new') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </section>
</x-layouts.panel>
