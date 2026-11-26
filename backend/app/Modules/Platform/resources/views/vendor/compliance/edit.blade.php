{{--
    Vendor › Settings › Compliance center (VEN-084, Figma Vendor Panel 91:117308): header with
    the open-actions badge, review banner (draft / under review / verified / needs action /
    rejected + the admin note), stepper Identity → Company → Bank → Documents → Terms (one card
    per step, client-side, ?step= remembered), Status card (state per step, jumps to it).
    One multipart form: "Save draft" posts to update, "Submit for review" to submit (data-action-url).
    Read-only while in review / verified or without settings.compliance.manage.
    Inputs: $profile, $sections, $missing, $step, $files, $countries, $accountTypes, $termsVersion,
    $termsUrl, $termsAccepted.
--}}
@use('App\Modules\Platform\Enums\ComplianceAccountType')
@use('App\Modules\Platform\Enums\ComplianceDocumentType')
@use('App\Modules\Platform\Enums\ComplianceSection')
@use('App\Modules\Platform\Enums\ComplianceSectionState')
@use('App\Modules\Platform\Enums\ComplianceStatus')
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('settings.compliance.manage');
    $status = $profile?->status ?? ComplianceStatus::Draft;
    $editable = $status->isEditableByVendor();
    $canEdit = $canManage && $editable;
    $showSecrets = $canManage;
    $locale = app()->getLocale();
    $attention = collect($sections)->filter(fn (ComplianceSectionState $state): bool => $state->needsAttention())->count();
    $sectionCases = ComplianceSection::cases();
    $total = count($sectionCases);
    $note = in_array($status, [ComplianceStatus::NeedsAction, ComplianceStatus::Rejected], true) ? $profile?->decision_note : null;
    $noteInSection = $note && collect($sections)->contains(ComplianceSectionState::NeedsAction);
    $isCompany = ($profile?->account_type ?? ComplianceAccountType::Company) === ComplianceAccountType::Company;
    $documentFor = fn (ComplianceDocumentType $type) => $profile?->documents->first(fn ($document) => $document->type === $type);
    $submitLabel = $profile?->submitted_at ? __('platform::vendor_compliance.buttons.resubmit') : __('platform::vendor_compliance.buttons.submit');
    $citiesUrl = vendor_route('settings.compliance.cities', ['country' => '__country__']);
    $date = fn ($value) => $value?->locale($locale)->translatedFormat('j M Y');
    $bannerTone = match ($status) {
        ComplianceStatus::Verified => 'success',
        ComplianceStatus::NeedsReview => 'info',
        ComplianceStatus::NeedsAction => 'warning',
        ComplianceStatus::Rejected => 'danger',
        default => 'neutral',
    };
    $bannerClasses = [
        'success' => 'border-success/30 bg-success-bg',
        'info' => 'border-info/30 bg-info-bg',
        'warning' => 'border-warning/30 bg-warning-bg',
        'danger' => 'border-danger/30 bg-danger-bg',
        'neutral' => 'border-line bg-subtle',
    ][$bannerTone];
    $bannerIcon = ['success' => 'check-circle', 'info' => 'clock', 'warning' => 'alert-triangle', 'danger' => 'x-circle', 'neutral' => 'info'][$bannerTone];
    $bannerText = match ($status) {
        ComplianceStatus::NeedsReview => __('platform::vendor_compliance.banner.needs_review', ['date' => $date($profile?->submitted_at)]),
        ComplianceStatus::Verified => __('platform::vendor_compliance.banner.verified', ['date' => $date($profile?->reviewed_at ?? $profile?->updated_at)]),
        default => __('platform::vendor_compliance.banner.'.$status->value),
    };
    $summary = [
        'identity' => $profile?->owner_name ?: __('platform::vendor_compliance.status_card.identity_empty'),
        'company' => $profile?->account_type === ComplianceAccountType::Individual
            ? __('platform::vendor_compliance.status_card.company_individual')
            : ($profile?->registration_number ? ComplianceDocumentType::CommercialRegistration->shortLabel().' '.\Illuminate\Support\Str::mask($profile->registration_number, '•', 4, max(0, mb_strlen($profile->registration_number) - 7)) : __('platform::vendor_compliance.status_card.company_empty')),
        'bank' => $profile?->maskedIban() ?? __('platform::vendor_compliance.status_card.bank_empty'),
        'documents' => $profile?->documents->filter(fn ($document) => in_array($document->type, ComplianceSection::Documents->documentTypes(), true))->map(fn ($document) => $document->type->label())->unique()->implode(', ') ?: __('platform::vendor_compliance.status_card.documents_empty'),
        'terms' => $termsAccepted
            ? __('platform::vendor_compliance.status_card.terms_accepted', ['date' => $date($profile?->terms_accepted_at)])
            : ($profile?->terms_accepted_at ? __('platform::vendor_compliance.status_card.terms_outdated') : __('platform::vendor_compliance.status_card.terms_pending')),
    ];
@endphp
<x-vendor::layout :title="__('platform::vendor_compliance.title')" page="vendor-compliance">
    <div class="grid grid-cols-1 gap-6" data-compliance data-step="{{ $step->value }}" data-cities-url="{{ $citiesUrl }}">
        <x-ui::page-header :title="__('platform::vendor_compliance.title')" :subtitle="__('platform::vendor_compliance.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('platform::vendor_compliance.title') => null]">
            <x-slot:status>
                @if ($editable && $attention > 0)
                    <x-ui::badge tone="warning">{{ trans_choice('platform::vendor_compliance.actions_needed', $attention, ['count' => $attention]) }}</x-ui::badge>
                @else
                    {{-- Same wording as the banner below (QA-P8-010): the vendor sees "Under review", not the admin's "Needs review". --}}
                    <x-ui::badge :tone="$status->tone()" data-compliance-status-badge>{{ __('platform::vendor_compliance.banner.'.$status->value.'_title') }}</x-ui::badge>
                @endif
            </x-slot:status>
            @if ($canEdit)
                <x-slot:actions>
                    <x-ui::button type="submit" form="compliance-form" variant="secondary" icon="check">{{ __('platform::vendor_compliance.buttons.save') }}</x-ui::button>
                    <x-ui::button
                        type="submit"
                        form="compliance-form"
                        icon="shield"
                        :data-action-url="vendor_route('settings.compliance.submit')"
                        data-confirm="{{ __('platform::vendor_compliance.submit_confirm.message') }}"
                        data-confirm-title="{{ __('platform::vendor_compliance.submit_confirm.title') }}"
                        data-confirm-label="{{ $submitLabel }}"
                        data-confirm-tone="primary"
                    >{{ $submitLabel }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="flex items-start gap-3 rounded-card border px-4 py-3 {{ $bannerClasses }}" role="status" id="compliance-banner">
            <x-ui::icon :name="$bannerIcon" :size="20" class="mt-0.5 shrink-0 text-ink-secondary" />
            <div class="grid gap-1 text-sm">
                <p class="font-semibold text-ink">{{ __('platform::vendor_compliance.banner.'.$status->value.'_title') }}</p>
                <p class="text-ink-secondary">{{ $bannerText }}</p>
                @if ($note && ! $noteInSection)
                    <p class="text-ink">{{ __('platform::vendor_compliance.banner.note', ['note' => $note]) }}</p>
                @endif
                @if (! $canManage)
                    <p class="text-ink-secondary">{{ __('platform::vendor_compliance.banner.readonly') }}</p>
                @endif
            </div>
        </div>

        <nav aria-label="{{ __('platform::vendor_compliance.steps_label') }}" class="relative overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <ol class="flex min-w-max items-center gap-3">
                @foreach ($sectionCases as $index => $section)
                    @php $state = $sections[$section->value]; @endphp
                    <li class="flex flex-1 items-center gap-3 last:flex-none">
                        <button
                            type="button"
                            class="group flex shrink-0 items-center gap-2.5 rounded-control py-1 text-sm text-ink-secondary outline-none focus-visible:ring-3 focus-visible:ring-primary/25 aria-[current=step]:font-medium aria-[current=step]:text-ink"
                            data-step-target="{{ $section->value }}"
                            @if ($section === $step) aria-current="step" @endif
                            @if ($state->isDone()) data-done @endif
                        >
                            <span @class([
                                'grid size-8 place-items-center rounded-full border text-[13px] font-medium transition-colors',
                                'group-aria-[current=step]:border-primary group-aria-[current=step]:bg-primary group-aria-[current=step]:text-on-primary',
                                'border-success bg-success text-white' => $state->isDone(),
                                'border-line-strong bg-surface text-ink-secondary' => ! $state->isDone(),
                            ])>
                                @if ($state->isDone())
                                    <x-ui::icon name="check" :size="16" class="group-aria-[current=step]:hidden" />
                                    <span class="hidden group-aria-[current=step]:inline" dir="ltr">{{ $index + 1 }}</span>
                                @else
                                    <span dir="ltr">{{ $index + 1 }}</span>
                                @endif
                            </span>
                            <span class="whitespace-nowrap">{{ $section->label() }}</span>
                            <span class="sr-only">— {{ $state->label() }}</span>
                        </button>
                        @unless ($loop->last)
                            <span @class(['h-0.5 min-w-8 flex-1 rounded-full', 'bg-success' => $state->isDone(), 'bg-line' => ! $state->isDone()]) aria-hidden="true"></span>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
            <form
                id="compliance-form"
                method="POST"
                action="{{ vendor_route('settings.compliance.update') }}"
                enctype="multipart/form-data"
                data-ajax
                data-unsaved-form
                novalidate
                class="grid gap-6"
            >
                @csrf
                <input type="hidden" name="step" value="{{ $step->value }}" data-step-input>

                @foreach ($sectionCases as $index => $section)
                    @php
                        $state = $sections[$section->value];
                        $sectionMissing = $missing[$section->value] ?? [];
                    @endphp
                    <div data-step-panel="{{ $section->value }}" @if ($section !== $step) hidden @endif>
                        <x-ui::card :id="'compliance-'.$section->value">
                            <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-ink-secondary">{{ __('platform::vendor_compliance.step_of', ['current' => $index + 1, 'total' => $total]) }}</p>
                                    <h2 class="text-base font-semibold text-ink">{{ __('platform::vendor_compliance.sections.'.$section->value.'.title') }}</h2>
                                    <p class="mt-1 text-sm text-ink-secondary">{{ __('platform::vendor_compliance.sections.'.$section->value.'.description') }}</p>
                                </div>
                                <x-ui::badge :tone="$state->tone()">{{ $state->label() }}</x-ui::badge>
                            </header>

                            @if ($note && $state === ComplianceSectionState::NeedsAction && $section !== ComplianceSection::Terms)
                                <div class="mb-5 flex items-start gap-3 rounded-control bg-warning-bg px-4 py-3 text-sm text-ink" role="note">
                                    <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
                                    <p>{{ __('platform::vendor_compliance.banner.note', ['note' => $note]) }}</p>
                                </div>
                            @endif

                            <fieldset class="grid gap-5" @disabled(! $canEdit)>
                                <legend class="sr-only">{{ __('platform::vendor_compliance.sections.'.$section->value.'.title') }}</legend>
                                @switch($section)
                                    @case(ComplianceSection::Identity)
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <x-ui::input name="owner_name" :label="__('platform::vendor_compliance.fields.owner_name')" :value="$profile?->owner_name" autocomplete="name" maxlength="255" required class="md:col-span-2" />
                                            <x-ui::input
                                                name="owner_id_number"
                                                :label="__('platform::vendor_compliance.fields.owner_id_number')"
                                                :value="$showSecrets ? $profile?->owner_id_number : ($profile?->owner_id_number ? \Illuminate\Support\Str::mask($profile->owner_id_number, '•', 1, max(0, mb_strlen($profile->owner_id_number) - 3)) : null)"
                                                :helper="! $showSecrets && $profile?->owner_id_number ? __('platform::vendor_compliance.helpers.masked') : null"
                                                autocomplete="off"
                                                dir="ltr"
                                                maxlength="100"
                                                required
                                            />
                                            <x-ui::date name="owner_id_expires_at" :label="__('platform::vendor_compliance.fields.owner_id_expires_at')" :value="$profile?->owner_id_expires_at?->toDateString()" />
                                            <x-ui::date name="owner_birth_date" :label="__('platform::vendor_compliance.fields.owner_birth_date')" :value="$profile?->owner_birth_date?->toDateString()" :max="now()->subDay()->toDateString()" />
                                            <x-ui::phone name="owner_phone" :label="__('platform::vendor_compliance.fields.owner_phone')" :value="$profile?->owner_phone" required />
                                        </div>
                                        @include('platform::vendor.compliance._document', ['type' => ComplianceDocumentType::NationalId, 'document' => $documentFor(ComplianceDocumentType::NationalId), 'hasFile' => ($files[$documentFor(ComplianceDocumentType::NationalId)?->id ?? 0] ?? false), 'required' => true, 'canEdit' => $canEdit])
                                        @break

                                    @case(ComplianceSection::Company)
                                        <x-ui::radio-group name="account_type" :label="__('platform::vendor_compliance.fields.account_type')" :options="$accountTypes" :value="$profile?->account_type?->value ?? ComplianceAccountType::Company->value" required data-account-type />
                                        <p class="-mt-2 text-xs text-ink-secondary" data-individual-only @if ($isCompany) hidden @endif>{{ __('platform::vendor_compliance.helpers.individual') }}</p>
                                        <div class="grid gap-4 md:grid-cols-2" data-company-only @if (! $isCompany) hidden @endif>
                                            <x-ui::input name="legal_name" :label="__('platform::vendor_compliance.fields.legal_name')" :value="$isCompany ? $profile?->legal_name : null" autocomplete="organization" maxlength="255" required class="md:col-span-2" />
                                            <x-ui::input name="registration_number" :label="__('platform::vendor_compliance.fields.registration_number')" :value="$profile?->registration_number" dir="ltr" maxlength="100" required />
                                            <x-ui::date name="registration_expires_at" :label="__('platform::vendor_compliance.fields.registration_expires_at')" :value="$profile?->registration_expires_at?->toDateString()" />
                                        </div>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <x-ui::input name="vat_number" :label="__('platform::vendor_compliance.fields.vat_number')" :value="$profile?->vat_number" dir="ltr" maxlength="100" />
                                            <x-ui::input name="company_email" type="email" :label="__('platform::vendor_compliance.fields.company_email')" :value="$profile?->company_email" autocomplete="email" dir="ltr" maxlength="255" required />
                                            <x-ui::select name="country_id" :label="__('platform::vendor_compliance.fields.country_id')" :options="$countries" :selected="$profile?->country_id" :placeholder="__('platform::vendor_compliance.placeholders.country')" required data-country-select />
                                            <x-ui::select
                                                name="city_id"
                                                :label="__('platform::vendor_compliance.fields.city_id')"
                                                :options="$profile?->city ? [$profile->city_id => $profile->city->name] : []"
                                                :selected="$profile?->city_id"
                                                :placeholder="$profile?->country_id ? __('platform::vendor_compliance.placeholders.city') : __('platform::vendor_compliance.placeholders.city_first')"
                                                :ajax-url="$profile?->country_id ? vendor_route('settings.compliance.cities', ['country' => $profile->country_id]) : null"
                                                :disabled="! $profile?->country_id"
                                                allow-clear
                                                data-city-select
                                                :data-placeholder-ready="__('platform::vendor_compliance.placeholders.city')"
                                            />
                                            <x-ui::input name="address" :label="__('platform::vendor_compliance.fields.address')" :value="$profile?->address" autocomplete="street-address" maxlength="500" required class="md:col-span-2" />
                                            <x-ui::phone name="company_phone" :label="__('platform::vendor_compliance.fields.company_phone')" :value="$profile?->company_phone" />
                                        </div>
                                        <div data-company-only @if (! $isCompany) hidden @endif>
                                            @include('platform::vendor.compliance._document', ['type' => ComplianceDocumentType::CommercialRegistration, 'document' => $documentFor(ComplianceDocumentType::CommercialRegistration), 'hasFile' => ($files[$documentFor(ComplianceDocumentType::CommercialRegistration)?->id ?? 0] ?? false), 'required' => true, 'canEdit' => $canEdit])
                                        </div>
                                        @break

                                    @case(ComplianceSection::Bank)
                                        <div class="grid gap-4">
                                            <x-ui::input name="bank_name" :label="__('platform::vendor_compliance.fields.bank_name')" :value="$profile?->bank_name" :placeholder="__('platform::vendor_compliance.placeholders.bank_name')" maxlength="255" required />
                                            <x-ui::input
                                                name="iban"
                                                :label="__('platform::vendor_compliance.fields.iban')"
                                                :value="$showSecrets ? $profile?->iban : $profile?->maskedIban()"
                                                :placeholder="__('platform::vendor_compliance.placeholders.iban')"
                                                :helper="$showSecrets ? __('platform::vendor_compliance.helpers.iban') : ($profile?->iban ? __('platform::vendor_compliance.helpers.masked') : null)"
                                                autocomplete="off"
                                                dir="ltr"
                                                maxlength="42"
                                                required
                                            />
                                            <x-ui::input name="account_name" :label="__('platform::vendor_compliance.fields.account_name')" :value="$profile?->account_name" maxlength="255" required />
                                        </div>
                                        @include('platform::vendor.compliance._document', ['type' => ComplianceDocumentType::BankLetter, 'document' => $documentFor(ComplianceDocumentType::BankLetter), 'hasFile' => ($files[$documentFor(ComplianceDocumentType::BankLetter)?->id ?? 0] ?? false), 'required' => false, 'canEdit' => $canEdit])
                                        @break

                                    @case(ComplianceSection::Documents)
                                        @foreach (ComplianceSection::Documents->documentTypes() as $type)
                                            @php $document = $documentFor($type); @endphp
                                            @include('platform::vendor.compliance._document', ['type' => $type, 'document' => $document, 'hasFile' => ($files[$document?->id ?? 0] ?? false), 'required' => false, 'canEdit' => $canEdit])
                                            @unless ($loop->last)
                                                <hr class="border-line">
                                            @endunless
                                        @endforeach
                                        @break

                                    @case(ComplianceSection::Terms)
                                        <p class="text-sm text-ink">{{ __('platform::vendor_compliance.terms.intro', ['version' => $termsVersion]) }}</p>
                                        @if ($termsUrl)
                                            <a href="{{ $termsUrl }}" target="_blank" rel="noopener" class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-ink-brand hover:underline">
                                                <x-ui::icon name="link" :size="16" />{{ __('platform::vendor_compliance.terms.read') }}
                                            </a>
                                        @endif
                                        @if ($termsAccepted)
                                            <p class="flex items-center gap-2 text-sm text-success">
                                                <x-ui::icon name="check-circle" :size="18" />
                                                {{ __('platform::vendor_compliance.terms.accepted', ['version' => $profile->terms_version, 'date' => $date($profile->terms_accepted_at)]) }}
                                            </p>
                                        @else
                                            @if ($profile?->terms_accepted_at)
                                                <p class="text-sm text-warning">{{ __('platform::vendor_compliance.terms.outdated', ['version' => $profile->terms_version]) }}</p>
                                            @endif
                                            <x-ui::checkbox name="accept_terms" :label="__('platform::vendor_compliance.terms.accept')" />
                                        @endif
                                        @break
                                @endswitch
                            </fieldset>

                            <footer class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                                @if ($index > 0)
                                    <x-ui::button variant="secondary" icon="chevron-left" class="rtl:[&_.ui-btn-icon]:rotate-180" :data-step-target="$sectionCases[$index - 1]->value">{{ __('platform::vendor_compliance.buttons.back') }}</x-ui::button>
                                @else
                                    <span></span>
                                @endif
                                @if ($index < $total - 1)
                                    <x-ui::button variant="secondary" icon-trailing="chevron-right" class="rtl:[&_.ui-btn-icon]:rotate-180" :data-step-target="$sectionCases[$index + 1]->value">{{ __('platform::vendor_compliance.buttons.next') }}</x-ui::button>
                                @elseif ($canEdit)
                                    <x-ui::button
                                        type="submit"
                                        icon="shield"
                                        :data-action-url="vendor_route('settings.compliance.submit')"
                                        data-confirm="{{ __('platform::vendor_compliance.submit_confirm.message') }}"
                                        data-confirm-title="{{ __('platform::vendor_compliance.submit_confirm.title') }}"
                                        data-confirm-label="{{ $submitLabel }}"
                                        data-confirm-tone="primary"
                                    >{{ $submitLabel }}</x-ui::button>
                                @endif
                            </footer>
                        </x-ui::card>
                    </div>
                @endforeach

                @if ($canEdit)
                    <div data-unsaved-bar hidden class="sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-overlay">
                        <p class="text-sm font-medium text-ink">{{ __('platform::vendor_compliance.unsaved') }}</p>
                        <div class="flex gap-3">
                            <x-ui::button variant="secondary" data-unsaved-discard>{{ __('platform::vendor_compliance.discard') }}</x-ui::button>
                            <x-ui::button type="submit" icon="check">{{ __('platform::vendor_compliance.buttons.save') }}</x-ui::button>
                        </div>
                    </div>
                @endif
            </form>

            <x-ui::card :title="__('platform::vendor_compliance.status_card.title')" id="compliance-status" class="lg:sticky lg:top-20">
                <ul class="grid">
                    @foreach ($sectionCases as $section)
                        @php $state = $sections[$section->value]; @endphp
                        <li class="border-b border-line last:border-0">
                            <button type="button" data-step-target="{{ $section->value }}" class="flex w-full items-center gap-3 py-3 text-start outline-none hover:bg-subtle/60 focus-visible:ring-3 focus-visible:ring-primary/25" aria-label="{{ __('platform::vendor_compliance.status_card.open_step', ['step' => $section->label()]) }}">
                                <span class="grid size-9 shrink-0 place-items-center rounded-control border border-line bg-subtle text-ink-secondary" aria-hidden="true"><x-ui::icon :name="$section->icon()" :size="18" /></span>
                                <span class="grid min-w-0 flex-1">
                                    <span class="truncate text-sm font-medium text-ink">{{ $section === ComplianceSection::Terms ? __('platform::vendor_compliance.status_card.terms_version', ['version' => $termsVersion]) : $section->label() }}</span>
                                    <span class="truncate text-xs text-ink-secondary"><bdi>{{ $summary[$section->value] }}</bdi></span>
                                </span>
                                <x-ui::badge :tone="$state->tone()">{{ $state->label() }}</x-ui::badge>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-ui::card>
        </div>
    </div>
</x-vendor::layout>
