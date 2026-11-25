{{--
    Store change review (ADM-018i): current vs requested countries / categories with the
    added and removed items highlighted, vendor note and the decision form (approve applies
    the list to the store; reject needs a note). requests.tenant-changes.manage only.
--}}
@php
    $locale = app()->getLocale();
    $store = $request->tenant?->getAttribute('name') ?? '—';
    $title = __('catalog::requests.tenant_changes.show_title', ['type' => $request->type->label(), 'store' => $store]);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-requests">
    <div class="grid gap-6" data-request-detail data-reload-on-change>
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('catalog::requests.breadcrumb') => route('admin.requests.index'), __('catalog::requests.tabs.tenant-changes') => route('admin.requests.tenant-changes.index'), $store => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$request->type->tone()">{{ $request->type->label() }}</x-ui::badge>
                <x-ui::badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui::badge>
            </x-slot:status>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="grid gap-6 md:grid-cols-2">
                @foreach (['current' => $current, 'requested' => $requested] as $column => $items)
                    <x-ui::card :title="__('catalog::requests.tenant_changes.'.$column)" :id="'change-'.$column">
                        @if ($items === [])
                            <p class="text-sm text-ink-secondary">{{ __('catalog::requests.tenant_changes.none') }}</p>
                        @else
                            <ul class="flex flex-wrap gap-2" role="list">
                                @foreach ($items as $id => $name)
                                    @php
                                        $isAdded = $column === 'requested' && in_array($id, $added, true);
                                        $isRemoved = $column === 'current' && in_array($id, $removed, true);
                                    @endphp
                                    <li>
                                        <x-ui::badge :tone="$isAdded ? 'success' : ($isRemoved ? 'danger' : 'neutral')" :variant="$isAdded || $isRemoved ? 'soft' : 'outline'">
                                            {{ $isAdded ? '+ ' : ($isRemoved ? '− ' : '') }}{{ $name }}
                                        </x-ui::badge>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui::card>
                @endforeach
                <x-ui::card :title="__('catalog::requests.tenant_changes.vendor_note')" id="change-vendor-note" class="md:col-span-2">
                    <p class="whitespace-pre-line text-sm text-ink-secondary">{{ $request->vendor_note ?: __('catalog::requests.edits.no_note') }}</p>
                    <p class="mt-3 text-xs text-ink-secondary">{{ $request->submitted_by }} · {{ $request->created_at?->locale($locale)->translatedFormat('j M Y, H:i') }}</p>
                </x-ui::card>
            </div>

            <x-ui::card :title="__('catalog::requests.tenant_changes.decision')" id="change-decision">
                @if ($request->isPending() && $canManage)
                    <form method="POST" action="{{ route('admin.requests.tenant-changes.decide', $request->id) }}" data-ajax data-reload-form data-decision-form novalidate class="grid gap-4">
                        @csrf
                        <input type="hidden" name="decision" value="approve" data-decision-input>
                        <p class="text-xs text-ink-secondary">{{ __('catalog::requests.tenant_changes.apply_hint', ['type' => mb_strtolower($request->type->label())]) }}</p>
                        <x-ui::textarea name="note" :label="__('catalog::requests.tenant_changes.note')" :helper="__('catalog::requests.tenant_changes.note_helper')" maxlength="1000" rows="4" />
                        <div class="flex flex-wrap justify-end gap-2">
                            <x-ui::button type="submit" variant="secondary" icon="x" data-decision="reject">{{ __('catalog::requests.tenant_changes.reject') }}</x-ui::button>
                            <x-ui::button type="submit" icon="check" data-decision="approve">{{ __('catalog::requests.tenant_changes.approve') }}</x-ui::button>
                        </div>
                    </form>
                @elseif ($request->reviewed_at)
                    <p class="text-sm text-ink">{{ __('catalog::requests.tenant_changes.reviewed', ['status' => $request->status->label(), 'admin' => $request->reviewer?->name ?? '—', 'date' => $request->reviewed_at->locale($locale)->translatedFormat('j M Y, H:i')]) }}</p>
                    @if ($request->admin_note)
                        <p class="mt-2 whitespace-pre-line text-sm text-ink-secondary">{{ $request->admin_note }}</p>
                    @endif
                @else
                    <p class="text-sm text-ink-secondary">{{ __('catalog::requests.thread.read_only') }}</p>
                @endif
            </x-ui::card>
        </div>
    </div>
</x-layouts.panel>
