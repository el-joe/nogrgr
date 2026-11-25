{{--
    Vendor profile › Compliance (ADM-041f, Figma 72:51480): KYC status and documents
    (type, masked number, status). "Review" opens the compliance page when allowed.
--}}
@php
    $canReview = $compliance && (bool) auth('admin')->user()?->can('vendors.kyc.view') && \Illuminate\Support\Facades\Route::has('admin.vendors.compliance.show');
@endphp
<x-ui::card :title="__('platform::vendors.compliance.title')" :subtitle="$compliance?->submitted_at ? __('platform::vendors.compliance.submitted', ['date' => $compliance->submitted_at->translatedFormat('j M Y')]) : null" id="vendor-compliance" class="max-w-3xl">
    @if ($compliance)
        <x-slot:actions>
            <div class="flex items-center gap-3">
                <x-ui::badge :tone="$compliance->status->tone()">{{ $compliance->status->label() }}</x-ui::badge>
                @if ($canReview)
                    <x-ui::button size="sm" variant="secondary" icon="shield" :href="route('admin.vendors.compliance.show', $compliance->id)">{{ __('platform::vendors.compliance.review') }}</x-ui::button>
                @endif
            </div>
        </x-slot:actions>
        @if ($compliance->documents->isEmpty())
            <x-ui::empty-state compact icon="file" :title="__('platform::vendors.compliance.empty_title')" :description="__('platform::vendors.compliance.empty_description')" />
        @else
            <ul class="grid">
                @foreach ($compliance->documents as $document)
                    <li class="flex items-center gap-3 border-b border-line py-3 last:border-0">
                        <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary" aria-hidden="true">
                            <x-ui::icon name="file" :size="18" />
                        </span>
                        <span class="grid min-w-0 flex-1">
                            <span class="truncate font-medium text-ink">{{ $document->type->label() }}</span>
                            <span class="truncate text-xs text-ink-secondary">
                                <bdi dir="ltr">{{ $document->maskedNumber() ?? __('platform::vendors.compliance.no_number') }}</bdi>
                                @if ($document->expires_at)
                                    · {{ __('platform::vendors.compliance.expires', ['date' => $document->expires_at->translatedFormat('j M Y')]) }}
                                @endif
                            </span>
                        </span>
                        <x-ui::badge :tone="$document->status->tone()">{{ $document->status->label() }}</x-ui::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        <x-ui::empty-state compact icon="shield" :title="__('platform::vendors.compliance.empty_title')" :description="__('platform::vendors.compliance.empty_description')" />
    @endif
</x-ui::card>
