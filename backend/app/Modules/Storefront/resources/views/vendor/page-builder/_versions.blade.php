{{-- Page builder › version history (VEN-032): published versions newest first, restore into the draft. --}}
@if ($versions->isEmpty())
    <x-ui::empty-state compact icon="clock" :title="__('content::page_builder.versions_empty_title')" :description="__('content::page_builder.versions_empty')" />
@else
    <ol class="grid gap-2" role="list">
        @foreach ($versions as $version)
            @php $live = $layout?->published_version_id === $version->id; @endphp
            <li class="flex flex-wrap items-center gap-3 rounded-control bg-subtle px-3 py-2.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-surface text-xs font-semibold text-ink" dir="ltr">v{{ $version->number }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-ink">{{ $version->created_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm') }}</p>
                    <p class="truncate text-xs text-ink-muted">{{ $version->published_by_name ? __('content::page_builder.published_by', ['name' => $version->published_by_name]) : __('content::page_builder.published_by_unknown') }}</p>
                </div>
                @if ($live)
                    <x-ui::badge tone="success">{{ __('content::page_builder.live') }}</x-ui::badge>
                @endif
                @if ($canManage)
                    <x-ui::button size="sm" variant="secondary" icon="refresh"
                        :data-confirm="__('content::page_builder.restore_message', ['number' => $version->number])"
                        :data-confirm-title="__('content::page_builder.restore_title', ['number' => $version->number])"
                        data-confirm-tone="primary"
                        :data-confirm-label="__('content::page_builder.restore')"
                        data-confirm-method="POST"
                        :data-confirm-url="vendor_route('store.page-builder.restore', ['target' => $target->key, 'version' => $version->id])">{{ __('content::page_builder.restore') }}</x-ui::button>
                @endif
            </li>
        @endforeach
    </ol>
@endif
