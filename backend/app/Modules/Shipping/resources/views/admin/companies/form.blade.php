{{--
    Admin › Logistics › Shipping › Companies › Company (ADM-033a–d, Figma Admin Panel
    71:46296 / 71:47208): Details tab (name, contact, tracking URL pattern, logo, active),
    Day rules tab (country rows with min / max days and default carrier), Stop periods tab
    (this company's and all-company periods) and the live delivery estimator. Read-only
    without shipping.companies.manage.
--}}
@php
    $isNew = ! $company->exists;
    $title = $isNew ? __('shipping::companies.create_title') : $company->name;
    $logo = $company->logoUrl();
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-shipping">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::companies.breadcrumb') => route('admin.shipping.companies.index'), ($isNew ? __('shipping::companies.new') : $company->name) => null]"
        >
            @unless ($isNew)
                <x-slot:status><x-ui::status :status="$company->is_active ? 'active' : 'inactive'" /></x-slot:status>
            @endunless
            <x-slot:actions>
                @if ($editable)
                    <x-ui::button variant="secondary" :href="route('admin.shipping.companies.index')">{{ __('shipping::common.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" form="company-form" icon="check">{{ __('shipping::common.save') }}</x-ui::button>
                @elseif (! $isNew && auth('admin')->user()?->can('shipping.companies.manage'))
                    <x-ui::button icon="edit" :href="route('admin.shipping.companies.edit', $company)">{{ __('shipping::common.edit') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::tabs id="company-tabs" :active="$tab" :label="$title" :items="array_filter([
                'details' => __('shipping::companies.tabs.details'),
                'day-rules' => $isNew ? null : ['label' => __('shipping::companies.tabs.day_rules'), 'count' => $dayRules->count()],
                'stop-periods' => $isNew ? null : ['label' => __('shipping::companies.tabs.stop_periods'), 'count' => $stopPeriods->count()],
            ])">
                <x-ui::tab-panel tabs="company-tabs" key="details" :active="$tab === 'details'" class="pt-6">
                    <form id="company-form" method="POST" action="{{ $isNew ? route('admin.shipping.companies.store') : route('admin.shipping.companies.update', $company) }}" enctype="multipart/form-data" data-ajax data-unsaved-form novalidate>
                        @csrf
                        @unless ($isNew)
                            @method('PUT')
                        @endunless
                        <fieldset @disabled(! $editable) class="grid gap-6">
                            <x-ui::card :title="__('shipping::companies.sections.details')">
                                <div class="grid gap-5">
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <x-ui::input name="name" :label="__('shipping::companies.fields.name')" :value="$company->name" required maxlength="120" />
                                        <x-ui::input name="contact_email" type="email" :label="__('shipping::companies.fields.contact_email')" :value="$company->contact_email" maxlength="191" dir="ltr" />
                                        <x-ui::phone name="contact_phone" :label="__('shipping::companies.fields.contact_phone')" :value="$company->contact_phone" />
                                        <x-ui::input name="website" type="url" :label="__('shipping::companies.fields.website')" :value="$company->website" maxlength="255" dir="ltr" placeholder="https://" />
                                    </div>
                                    <x-ui::input name="tracking_url_pattern" :label="__('shipping::companies.fields.tracking_url')" :value="$company->tracking_url_pattern" maxlength="500" dir="ltr" placeholder="https://www.example.com/track/{tracking}" :helper="__('shipping::companies.fields.tracking_help')" />
                                    <x-ui::image-upload name="logo[]" :label="__('shipping::companies.fields.logo')" :images="$logo ? [['id' => 'logo', 'url' => $logo, 'alt' => $company->name]] : []" :max="1" accept="image/png,image/jpeg,image/webp" :helper="__('shipping::companies.fields.logo_help')" />
                                    <x-ui::switch name="is_active" :label="__('shipping::companies.fields.active')" :checked="(bool) $company->is_active" />
                                </div>
                            </x-ui::card>
                        </fieldset>
                        @if ($editable)
                            <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6">
                                <p class="text-sm font-medium text-ink">{{ __('shipping::common.unsaved') }}</p>
                                <div class="flex gap-3">
                                    <x-ui::button variant="secondary" data-unsaved-discard>{{ __('shipping::common.discard') }}</x-ui::button>
                                    <x-ui::button type="submit" icon="check">{{ __('shipping::common.save') }}</x-ui::button>
                                </div>
                            </div>
                        @endif
                    </form>
                </x-ui::tab-panel>

                @unless ($isNew)
                    <x-ui::tab-panel tabs="company-tabs" key="day-rules" :active="$tab === 'day-rules'" class="pt-6">
                        <x-ui::card :title="__('shipping::companies.day_rules.title')" :padding="false">
                            @if ($editable)
                                <x-slot:actions>
                                    <x-ui::button variant="secondary" size="sm" icon="plus" data-remote-modal="day-rule-modal" :data-url="route('admin.shipping.companies.day-rules.create', $company)">{{ __('shipping::companies.day_rules.add') }}</x-ui::button>
                                </x-slot:actions>
                            @endif
                            @if ($dayRules->isEmpty())
                                <div class="p-5">
                                    <x-ui::empty-state compact icon="calendar" :title="__('shipping::companies.day_rules.empty_title')" :description="__('shipping::companies.day_rules.empty_description')" />
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[520px] text-sm">
                                        <thead class="bg-subtle text-xs text-ink-secondary">
                                            <tr>
                                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('shipping::companies.day_rules.country') }}</th>
                                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('shipping::companies.day_rules.min_days') }}</th>
                                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('shipping::companies.day_rules.max_days') }}</th>
                                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('shipping::companies.day_rules.default') }}</th>
                                                <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('shipping::common.actions') }}</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($dayRules as $dayRule)
                                                <tr>
                                                    <td class="px-5 py-3 text-ink">{{ trim($dayRule->country->flag.' '.$dayRule->country->name) }}</td>
                                                    <td class="px-5 py-3 text-end text-ink" dir="ltr">{{ $dayRule->min_days }}</td>
                                                    <td class="px-5 py-3 text-end text-ink" dir="ltr">{{ $dayRule->max_days }}</td>
                                                    <td class="px-5 py-3">
                                                        @if (in_array($dayRule->country_id, $defaultCountryIds, true))
                                                            <x-ui::status status="active">{{ __('shipping::companies.day_rules.default_badge') }}</x-ui::status>
                                                        @else
                                                            <x-ui::status status="inactive">—</x-ui::status>
                                                        @endif
                                                    </td>
                                                    <td class="px-5 py-3">
                                                        @if ($editable)
                                                            <div class="flex items-center justify-end gap-1">
                                                                <x-ui::icon-button icon="edit" size="sm" :label="__('shipping::companies.day_rules.edit_named', ['country' => $dayRule->country->name])" data-remote-modal="day-rule-modal" :data-url="route('admin.shipping.companies.day-rules.edit', [$company, $dayRule->id])" />
                                                                <x-ui::icon-button icon="trash" size="sm" :label="__('shipping::companies.day_rules.delete_named', ['country' => $dayRule->country->name])" :data-confirm="__('shipping::companies.day_rules.delete_message')" :data-confirm-title="__('shipping::companies.day_rules.delete_named', ['country' => $dayRule->country->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.companies.day-rules.destroy', [$company, $dayRule->id])" />
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </x-ui::card>
                    </x-ui::tab-panel>

                    <x-ui::tab-panel tabs="company-tabs" key="stop-periods" :active="$tab === 'stop-periods'" class="pt-6">
                        <x-ui::card :title="__('shipping::companies.stops.title')" :subtitle="__('shipping::companies.stops.subtitle')">
                            <x-slot:actions>
                                <x-ui::button variant="secondary" size="sm" icon="calendar" :href="route('admin.shipping.stop-periods.index', ['company' => $company->id])">{{ __('shipping::companies.stops.calendar') }}</x-ui::button>
                            </x-slot:actions>
                            @include('shipping::admin.stop-periods._list', ['periods' => $stopPeriods, 'canManage' => $editable && auth('admin')->user()?->can('shipping.stop-periods.manage')])
                            @if ($editable && auth('admin')->user()?->can('shipping.stop-periods.manage'))
                                <div class="mt-4">
                                    <x-ui::button variant="secondary" size="sm" icon="plus" data-remote-modal="stop-period-modal" :data-url="route('admin.shipping.stop-periods.create', ['company' => $company->id])">{{ __('shipping::stop_periods.add') }}</x-ui::button>
                                </div>
                            @endif
                        </x-ui::card>
                    </x-ui::tab-panel>
                @endunless
            </x-ui::tabs>

            <x-ui::card :title="__('shipping::companies.estimator.title')" :subtitle="__('shipping::companies.estimator.subtitle')">
                <form class="grid gap-4" data-estimator data-url="{{ route('admin.shipping.companies.estimate') }}" @if ($company->exists) data-company="{{ $company->id }}" @endif novalidate>
                    <x-ui::select name="country_id" :label="__('shipping::companies.estimator.country')" :options="$countries" :selected="$dayRules->first()?->country_id" :placeholder="__('shipping::companies.estimator.country')" data-estimate-field />
                    <x-ui::date name="date" :label="__('shipping::companies.estimator.date')" :value="now()->toDateString()" required data-estimate-field />
                    <p data-estimate-result class="flex items-start gap-2 rounded-control bg-subtle px-3 py-2.5 text-[13px] text-ink-secondary" role="status" aria-live="polite">
                        <x-ui::icon name="check-circle" :size="16" class="mt-0.5 shrink-0" />
                        <span><span data-estimate-text>{{ __('shipping::companies.estimator.hint') }}</span> <span data-estimate-skips class="block text-ink-secondary"></span></span>
                    </p>
                    <p data-estimate-error hidden class="flex items-start gap-2 rounded-control bg-danger-bg px-3 py-2.5 text-[13px] text-danger" role="alert">
                        <x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0" />
                        <span>{{ __('shipping::companies.estimator.error') }}</span>
                    </p>
                </form>
            </x-ui::card>
        </div>
    </div>

    @if ($editable && ! $isNew)
        <x-ui::modal id="day-rule-modal" :title="__('shipping::companies.day_rules.create_title')" size="sm">
            @include('shipping::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('shipping::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="day-rule-form" icon="check" data-remote-submit>{{ __('shipping::common.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
        @include('shipping::admin.stop-periods._modal')
    @endif
</x-layouts.panel>
