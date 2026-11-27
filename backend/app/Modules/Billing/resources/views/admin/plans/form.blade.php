{{--
    Admin › Vendors › Plans › Create / Edit (ADM-046a, Figma 73:58030): plan card
    (translated name / description / highlights per locale, icon, monthly + yearly USD
    price, trial days, sort), Limits card (empty = unlimited) and AI & add-ons switches.
    Sticky "Unsaved changes" bar (resources/js/panels/admin/billing.js).
--}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $isNew = ! $plan->exists;
    $title = $isNew ? __('billing::plans.create_title') : __('billing::plans.edit_title', ['name' => $plan->name]);
    $money = fn (int $minor) => \App\Shared\Money\Money::ofMinor($minor)->toDecimal();
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="billing">
    <form
        id="plan-form"
        method="POST"
        action="{{ $isNew ? route('admin.plans.store') : route('admin.plans.update', $plan) }}"
        data-ajax
        data-unsaved-form
        class="grid gap-6"
        novalidate
    >
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.vendors') => null, __('billing::plans.title') => route('admin.plans.index'), ($isNew ? __('billing::plans.new') : $plan->name) => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.plans.index')">{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('billing::plans.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-2">
            <x-ui::card :title="__('billing::plans.sections.plan')">
                <div class="grid gap-5">
                    <x-ui::locale-tabs id="plan-i18n">
                        @foreach ($locales as $code => $language)
                            <x-ui::locale-pane parent="plan-i18n" :locale="$code">
                                <div class="grid gap-5">
                                    <x-ui::input :name="'name['.$code.']'" :label="__('billing::plans.fields.name')" :value="$plan->getTranslation('name', $code, false)" required :dir="$language['dir']" />
                                    <x-ui::input :name="'description['.$code.']'" :label="__('billing::plans.fields.description')" :value="$plan->getTranslation('description', $code, false)" :dir="$language['dir']" />
                                    <x-ui::textarea :name="'highlights['.$code.']'" :label="__('billing::plans.fields.highlights')" :value="$plan->getTranslation('highlights', $code, false)" :rows="4" :maxlength="2000" :dir="$language['dir']" />
                                </div>
                            </x-ui::locale-pane>
                        @endforeach
                    </x-ui::locale-tabs>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui::icon-picker name="icon" :label="__('billing::plans.fields.icon')" :selected="$plan->icon" :placeholder="__('billing::plans.fields.icon_placeholder')" />
                        <x-ui::select name="status" :label="__('billing::plans.fields.status')" :options="$statuses" :selected="$plan->status?->value" :search="false" required />
                        <x-ui::input name="monthly_price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('billing::plans.fields.monthly_price')" :value="$money((int) $plan->monthly_price_minor)" required dir="ltr" />
                        <x-ui::input name="yearly_price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('billing::plans.fields.yearly_price')" :value="$money((int) $plan->yearly_price_minor)" required dir="ltr" />
                        <x-ui::input name="trial_days" type="number" min="0" max="365" :label="__('billing::plans.fields.trial_days')" :value="$plan->trial_days" required dir="ltr" />
                        <x-ui::input name="sort_order" type="number" min="0" :label="__('billing::plans.fields.sort_order')" :value="$plan->sort_order" dir="ltr" />
                    </div>
                </div>
            </x-ui::card>

            <div class="grid gap-6">
                <x-ui::card :title="__('billing::plans.sections.limits')" :subtitle="__('billing::plans.sections.limits_hint')">
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($limits as $limit)
                            <x-ui::input :name="'limits['.$limit->value.']'" type="number" min="0" :label="$limit->label()" :value="$plan->limit($limit)" :placeholder="__('billing::plans.unlimited')" dir="ltr" />
                        @endforeach
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('billing::plans.sections.features')">
                    <div class="grid gap-4">
                        @foreach ($features as $feature)
                            <x-ui::switch :name="'features['.$feature->value.']'" :label="$feature->label()" :checked="$plan->hasFeature($feature)" />
                        @endforeach
                    </div>
                </x-ui::card>
            </div>
        </div>

        <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
            <p class="text-sm font-medium text-ink">{{ __('billing::plans.unsaved') }}</p>
            <div class="flex gap-3">
                <x-ui::button variant="secondary" data-unsaved-discard>{{ __('billing::plans.discard') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('billing::plans.save_changes') }}</x-ui::button>
            </div>
        </div>
    </form>
</x-layouts.panel>
