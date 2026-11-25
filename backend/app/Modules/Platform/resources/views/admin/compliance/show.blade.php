{{--
    Admin › Vendors › Compliance › store review (ADM-044a, Figma 73:54733): one card per
    document (preview + fields), Bank account (masked IBAN), Decision note (note to the
    vendor + accepted-terms line). Header: status, "Needs action" (note required),
    "Verify all" and Reject in the more menu (vendors.kyc.manage, only valid transitions).
--}}
@use('App\Modules\Platform\Enums\ComplianceStatus')
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('vendors.kyc.manage');
    $canStore = (bool) $admin?->can('vendors.view');
    $store = $profile->tenant?->name ?? $profile->legal_name ?? __('platform::compliance.unknown_store');
    $title = __('platform::compliance.show_title', ['store' => $store]);
    $locale = app()->getLocale();
    $canNeedsAction = $canManage && $profile->status->canTransitionTo(ComplianceStatus::NeedsAction);
    $canVerify = $canManage && $profile->status->canTransitionTo(ComplianceStatus::Verified);
    $canReject = $canManage && $profile->status->canTransitionTo(ComplianceStatus::Rejected);
    $canDecide = $canNeedsAction || $canVerify || $canReject;
    $iban = $profile->maskedIban();
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-vendor-reviews">
    <div class="grid gap-6" data-reviews="compliance-review" data-reviews-reload>
        <x-ui::page-header
            :title="$title"
            :subtitle="$profile->submitted_at ? __('platform::compliance.submitted_on', ['date' => $profile->submitted_at->locale($locale)->translatedFormat('j M Y, H:i')]) : __('platform::compliance.not_submitted')"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::compliance.title') => route('admin.vendors.compliance.index'), $store => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$profile->status->tone()">{{ $profile->status->label() }}</x-ui::badge>
                <x-ui::badge :tone="$profile->risk->tone()" variant="outline">{{ __('platform::compliance.risk_line', ['risk' => $profile->risk->label()]) }}</x-ui::badge>
            </x-slot:status>
            @if ($canDecide || $canStore)
                <x-slot:actions>
                    @if ($canNeedsAction)
                        <x-ui::button variant="secondary" icon="alert-triangle" data-decision="needs_action">{{ __('platform::compliance.decision.needs_action') }}</x-ui::button>
                    @endif
                    @if ($canVerify)
                        <x-ui::button icon="check" data-decision="verified">{{ __('platform::compliance.decision.verify_all') }}</x-ui::button>
                    @endif
                    @if ($canReject || $canStore)
                        <x-ui::row-actions :label="__('platform::compliance.actions.more', ['store' => $store])">
                            @if ($canStore)
                                <x-ui::dropdown-item icon="store" :href="route('admin.vendors.show', $profile->tenant_id)">{{ __('platform::compliance.actions.store_profile') }}</x-ui::dropdown-item>
                            @endif
                            @if ($canReject)
                                @if ($canStore)
                                    <x-ui::dropdown-divider />
                                @endif
                                <x-ui::dropdown-item
                                    icon="x-circle"
                                    tone="danger"
                                    data-decision="rejected"
                                    :data-confirm="__('platform::compliance.decision.reject_message')"
                                    :data-confirm-title="__('platform::compliance.decision.reject_title', ['store' => $store])"
                                    :data-confirm-label="__('platform::compliance.decision.reject')"
                                >{{ __('platform::compliance.decision.reject') }}</x-ui::dropdown-item>
                            @endif
                        </x-ui::row-actions>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_370px]">
            <div class="grid gap-6">
                @forelse ($profile->documents as $document)
                    @include('platform::admin.compliance._document-card', ['document' => $document, 'hasFile' => $files[$document->id] ?? false, 'canManage' => $canManage])
                @empty
                    <x-ui::card id="no-documents">
                        <x-ui::empty-state icon="file" :title="__('platform::compliance.no_documents_title')" :description="__('platform::compliance.no_documents_description')" />
                    </x-ui::card>
                @endforelse
            </div>

            <div class="grid gap-6">
                <x-ui::card :title="__('platform::compliance.bank.title')" id="bank-account">
                    @if ($profile->bank_name || $iban || $profile->account_name)
                        <dl class="grid gap-3 text-sm">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-ink-secondary">{{ __('platform::compliance.bank.bank') }}</dt>
                                <dd class="text-end font-medium text-ink">{{ $profile->bank_name ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-ink-secondary">{{ __('platform::compliance.bank.iban') }}</dt>
                                <dd class="text-end font-medium text-ink"><bdi dir="ltr">{{ $iban ?? '—' }}</bdi></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-ink-secondary">{{ __('platform::compliance.bank.account_name') }}</dt>
                                <dd class="text-end font-medium text-ink">{{ $profile->account_name ?? '—' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="flex items-center gap-2 text-sm text-ink-secondary">
                            <x-ui::icon name="wallet" :size="18" />
                            {{ __('platform::compliance.bank.empty') }}
                        </p>
                    @endif
                </x-ui::card>

                <x-ui::card :title="__('platform::compliance.decision.title')" id="decision-note">
                    <form id="compliance-decision-form" method="POST" action="{{ route('admin.vendors.compliance.decide', $profile->id) }}" data-ajax data-decision-form novalidate class="grid gap-3">
                        @csrf
                        <input type="hidden" name="status" value="">
                        <x-ui::textarea
                            name="note"
                            :label="__('platform::compliance.decision.note')"
                            :value="$profile->decision_note"
                            :helper="$canDecide ? __('platform::compliance.decision.note_helper') : null"
                            :disabled="! $canDecide"
                            maxlength="2000"
                            rows="4"
                        />
                    </form>
                    @if ($profile->reviewed_at)
                        <p class="mt-3 text-xs text-ink-secondary">
                            {{ __('platform::compliance.decision.last', [
                                'admin' => $profile->reviewer?->name ?? __('platform::compliance.decision.unknown_admin'),
                                'date' => $profile->reviewed_at->locale($locale)->translatedFormat('j M Y, H:i'),
                            ]) }}
                        </p>
                    @endif
                    <p class="mt-3 text-xs text-ink-secondary">
                        @if ($profile->terms_version && $profile->terms_accepted_at)
                            {{ __('platform::compliance.terms_line', [
                                'version' => $profile->terms_version,
                                'date' => $profile->terms_accepted_at->locale($locale)->translatedFormat('j M Y, H:i'),
                                'ip' => $profile->terms_accepted_ip ?? '—',
                            ]) }}
                        @else
                            {{ __('platform::compliance.terms_missing') }}
                        @endif
                    </p>
                </x-ui::card>
            </div>
        </div>
    </div>
</x-layouts.panel>
