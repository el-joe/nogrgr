{{--
    Product edit request detail (ADM-018b, Figma 68:30673): per-field diff (red = central,
    green = requested) per locale, requested images, request facts, vendor note and the
    Approve (→ tenant override) / Reject (reason modal) actions for requests.edits.manage.
--}}
@use('App\Modules\Catalog\Enums\EditableProductField')
@php
    $locale = app()->getLocale();
    $store = $request->tenant?->getAttribute('name') ?? '—';
    $product = $request->product;
    $productName = $product?->name ?? '—';
    $title = __('catalog::requests.edits.show_title', ['reference' => $request->reference()]);
    $textChanges = collect($request->changes)->reject(fn (array $change) => $change['field'] === EditableProductField::Images->value);
    $imageChange = collect($request->changes)->firstWhere('field', EditableProductField::Images->value);
    $localeNames = ['ar' => __('ui.languages.ar'), 'en' => __('ui.languages.en')];
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-requests">
    <div class="grid gap-6" data-request-detail data-reload-on-change>
        <x-ui::page-header
            :title="$title"
            :subtitle="trans_choice('catalog::requests.edits.summary', count($request->changes), ['store' => $store, 'count' => count($request->changes), 'product' => $productName])"
            :breadcrumbs="[__('catalog::requests.breadcrumb') => route('admin.requests.index'), __('catalog::requests.tabs.edits') => route('admin.requests.edits.index'), $request->reference() => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui::badge>
            </x-slot:status>
            @if ($canManage && $request->isPending())
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="x" data-overlay-open="edit-reject-modal">{{ __('catalog::requests.edits.reject') }}</x-ui::button>
                    <x-ui::button
                        icon="check"
                        data-request-action
                        :data-url="route('admin.requests.edits.approve', $request->id)"
                        data-method="POST"
                        :data-confirm-text="__('catalog::requests.edits.approve_message')"
                        :data-confirm-title="__('catalog::requests.edits.approve_title', ['reference' => $request->reference()])"
                        data-confirm-tone="brand"
                    >{{ __('catalog::requests.edits.approve', ['store' => $store]) }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::card :title="__('catalog::requests.edits.changes')" :subtitle="__('catalog::requests.edits.legend')" id="edit-changes">
                <div class="grid gap-5">
                    @foreach ($textChanges as $change)
                        @php($field = EditableProductField::tryFrom((string) $change['field']))
                        <section class="grid gap-2">
                            <h3 class="text-sm font-medium text-ink">{{ $field?->label() ?? $change['field'] }} @if (! empty($change['locale']))({{ $localeNames[$change['locale']] ?? strtoupper($change['locale']) }})@endif</h3>
                            <div class="grid gap-3 sm:grid-cols-2" @if (! empty($change['locale'])) dir="{{ $change['locale'] === 'ar' ? 'rtl' : 'ltr' }}" @endif>
                                <div class="rounded-control border-s-2 border-danger bg-danger-bg px-3 py-2">
                                    <p class="text-xs font-medium text-danger">− {{ __('catalog::requests.edits.current') }}</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm text-ink">{{ filled($change['current']) ? $change['current'] : __('catalog::requests.edits.empty_value') }}</p>
                                </div>
                                <div class="rounded-control border-s-2 border-success bg-success-bg px-3 py-2">
                                    <p class="text-xs font-medium text-success">+ {{ __('catalog::requests.edits.requested') }}</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm text-ink">{{ $change['requested'] }}</p>
                                </div>
                            </div>
                        </section>
                    @endforeach

                    @if ($imageChange)
                        <section class="grid gap-3 rounded-card bg-subtle p-4">
                            <div>
                                <h3 class="text-sm font-semibold text-ink">{{ __('catalog::requests.edits.images') }}</h3>
                                <p class="text-xs text-ink-secondary">{{ trans_choice('catalog::requests.edits.images_count', count($request->files ?? []), ['count' => count($request->files ?? [])]) }}</p>
                            </div>
                            <ul class="flex flex-wrap gap-3" role="list">
                                @foreach ($request->files ?? [] as $index => $file)
                                    <li class="relative">
                                        <a href="{{ route('admin.requests.edits.file', [$request->id, $index]) }}" target="_blank" rel="noopener" class="block size-20 overflow-hidden rounded-control border border-line bg-surface">
                                            <img src="{{ route('admin.requests.edits.file', [$request->id, $index]) }}" alt="{{ $file['name'] }}" class="size-full object-contain bg-media" loading="lazy">
                                        </a>
                                        @if ($loop->first)
                                            <span class="absolute bottom-1 start-1 rounded-sm bg-ink px-1.5 text-[10px] font-medium text-surface">{{ __('catalog::requests.edits.main') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            <div>
                                <h4 class="text-xs font-medium text-ink-secondary">{{ __('catalog::requests.edits.current_images') }}</h4>
                                @if (! empty($imageChange['current']))
                                    <ul class="mt-2 flex flex-wrap gap-2" role="list">
                                        @foreach ((array) $imageChange['current'] as $path)
                                            <li class="size-12 overflow-hidden rounded-control border border-line bg-surface opacity-70"><img src="{{ $service->publicUrl((string) $path) }}" alt="" class="size-full object-contain bg-media" loading="lazy"></li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1 text-xs text-ink-secondary">{{ __('catalog::requests.edits.no_current_images') }}</p>
                                @endif
                            </div>
                        </section>
                    @endif
                </div>
            </x-ui::card>

            <div class="grid gap-6">
                <x-ui::card :title="__('catalog::requests.edits.request')" id="edit-request-facts">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::requests.edits.vendor') }}</dt><dd class="text-end font-medium text-ink">{{ $store }}</dd></div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('catalog::requests.edits.product') }}</dt>
                            <dd class="text-end font-medium text-ink">
                                @if ($product && auth('admin')->user()?->can('catalog.products.view'))
                                    <a href="{{ route('admin.catalog.products.edit', $product->id) }}" class="text-ink-brand hover:underline"><bdi dir="ltr">{{ $product->sku }}</bdi></a>
                                @else
                                    <bdi dir="ltr">{{ $product?->sku ?? '—' }}</bdi>
                                @endif
                            </dd>
                        </div>
                        @if ($request->variant)
                            <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::requests.edits.variant') }}</dt><dd class="text-end font-medium text-ink">{{ $request->variant->label() }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::requests.edits.submitted') }}</dt><dd class="text-end font-medium text-ink">{{ $request->created_at?->locale($locale)->translatedFormat('j M, H:i') }}</dd></div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('catalog::requests.edits.other_overrides') }}</dt>
                            <dd class="text-end font-medium text-ink">
                                @if ($otherOverrides->isEmpty())
                                    {{ __('catalog::requests.edits.other_overrides_none') }}
                                @else
                                    {{ $otherOverrides->count() }} ({{ $otherOverrides->map(fn (array $override) => EditableProductField::tryFrom($override['field'])?->label() ?? $override['field'])->unique()->implode(', ') }})
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if ($request->reviewed_at)
                        <p class="mt-4 border-t border-line pt-3 text-xs text-ink-secondary">
                            {{ __('catalog::requests.edits.reviewed', ['status' => $request->status->label(), 'admin' => $request->reviewer?->name ?? '—', 'date' => $request->reviewed_at->locale($locale)->translatedFormat('j M Y, H:i')]) }}
                            @if ($request->reason)
                                <br>{{ __('catalog::requests.edits.reviewed_reason', ['reason' => $request->reason]) }}
                            @endif
                        </p>
                    @endif
                </x-ui::card>

                <x-ui::card :title="__('catalog::requests.edits.vendor_note')" id="edit-vendor-note">
                    <p class="whitespace-pre-line text-sm text-ink-secondary">{{ $request->vendor_note ?: __('catalog::requests.edits.no_note') }}</p>
                </x-ui::card>

                <p class="flex items-start gap-2 rounded-card bg-info-bg px-4 py-3 text-xs text-ink">
                    <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0 text-info" />
                    {{ __('catalog::requests.edits.info') }}
                </p>
            </div>
        </div>
    </div>

    @if ($canManage && $request->isPending())
        <x-ui::modal id="edit-reject-modal" :title="__('catalog::requests.edits.reject_title')" :subtitle="__('catalog::requests.edits.reject_subtitle')" size="sm">
            <form id="edit-reject-form" method="POST" action="{{ route('admin.requests.edits.reject', $request->id) }}" data-ajax data-reload-form novalidate class="grid gap-4">
                @csrf
                <x-ui::textarea name="reason" :label="__('catalog::requests.edits.reason')" :placeholder="__('catalog::requests.edits.reason_placeholder')" maxlength="1000" rows="4" required />
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="edit-reject-form" variant="danger" icon="x">{{ __('catalog::requests.edits.reject') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
