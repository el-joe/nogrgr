{{--
    Vendor › Your store › Custom theme (VEN-036, Figma Vendor Panel 200:36959 aligned to
    Tenant Panel): storefront status card (live version, custom / built-in switch, last
    render problem), upload card (ZIP → validation report), versions table (status, files,
    preview, submit, publish / roll back, delete), links to the starter kit (VEN-036a),
    theme settings (VEN-036b) and the developer guide.
--}}
@php
    use App\Modules\Storefront\Enums\CustomThemeVersionStatus;

    $live = $theme?->live_version_id !== null ? $versions->firstWhere('id', $theme->live_version_id) : null;
@endphp
<x-vendor::layout :title="__('content::custom_themes.title')" page="vendor-custom-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="__('content::custom_themes.title')"
            :subtitle="__('content::custom_themes.subtitle')"
            :breadcrumbs="[__('content::custom_themes.crumb') => null, __('content::custom_themes.title') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="lifebuoy" :href="vendor_route('store.custom-themes.docs')">{{ __('content::custom_themes.docs_link') }}</x-ui::button>
                <x-ui::button variant="secondary" icon="download" :href="vendor_route('store.custom-themes.starter-kit')">{{ __('content::custom_themes.starter_kit') }}</x-ui::button>
                @if ($theme)
                    <x-ui::button variant="secondary" icon="settings" :href="vendor_route('store.custom-themes.settings')">{{ __('content::custom_themes.settings_link') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <x-ui::card :title="__('content::custom_themes.status_card.title')" id="custom-theme-status">
                <div class="grid gap-4 text-sm text-ink">
                    @if (! $theme)
                        <p class="text-ink-secondary">{{ __('content::custom_themes.status_card.none') }}</p>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui::badge :tone="$theme->is_active && $live ? 'success' : 'neutral'">{{ $theme->is_active && $live ? __('content::custom_themes.status_card.active') : __('content::custom_themes.status_card.inactive') }}</x-ui::badge>
                        </div>
                        <p>{{ $live ? __('content::custom_themes.status_card.live', ['name' => $live->name, 'version' => $live->version]) : __('content::custom_themes.status_card.no_live') }}</p>
                        @if ($theme->last_render_error && $theme->last_render_error_at)
                            <div class="flex items-start gap-3 rounded-card bg-danger-bg px-4 py-3 text-danger" role="alert" data-render-error>
                                <x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0" />
                                <span class="min-w-0 break-words">{{ __('content::custom_themes.status_card.last_error', ['date' => $theme->last_render_error_at->isoFormat('D MMM YYYY, HH:mm'), 'error' => $theme->last_render_error]) }}</span>
                            </div>
                        @endif
                        @if ($canManage && $live)
                            <div>
                                @if ($theme->is_active)
                                    <x-ui::button variant="secondary" icon="refresh" :href="vendor_route('store.custom-themes.active', ['active' => 0])" data-confirm="{{ __('content::custom_themes.status_card.switch_off_confirm') }}" data-confirm-title="{{ __('content::custom_themes.status_card.switch_off') }}" data-confirm-tone="warning" data-confirm-method="PATCH">{{ __('content::custom_themes.status_card.switch_off') }}</x-ui::button>
                                @else
                                    <x-ui::button icon="check" :href="vendor_route('store.custom-themes.active', ['active' => 1])" data-confirm="{{ __('content::custom_themes.status_card.switch_on_confirm') }}" data-confirm-title="{{ __('content::custom_themes.status_card.switch_on') }}" data-confirm-tone="primary" data-confirm-method="PATCH">{{ __('content::custom_themes.status_card.switch_on') }}</x-ui::button>
                                @endif
                            </div>
                        @endif
                    @endif
                    <p class="flex items-start gap-2 text-xs text-ink-secondary">
                        <x-ui::icon name="lock" :size="14" class="mt-0.5 shrink-0" />
                        <span>{{ __('content::custom_themes.status_card.secure_note') }}</span>
                    </p>
                </div>
            </x-ui::card>

            @if ($canManage)
                <x-ui::card :title="__('content::custom_themes.upload.title')" id="custom-theme-upload">
                    <form method="POST" action="{{ vendor_route('store.custom-themes.upload') }}" enctype="multipart/form-data" data-ajax novalidate class="grid gap-4" id="custom-theme-upload-form">
                        @csrf
                        <x-ui::file-upload name="package" :label="__('content::custom_themes.upload.field')" accept=".zip,application/zip" :hint="__('content::custom_themes.upload.hint', ['mb' => $limits['zip_max_mb']])" required />
                        <div>
                            <x-ui::button type="submit" icon="upload">{{ __('content::custom_themes.upload.submit') }}</x-ui::button>
                        </div>
                    </form>
                </x-ui::card>
            @endif
        </div>

        <x-ui::card :title="__('content::custom_themes.versions.title')" :padding="false" id="custom-theme-versions">
            @if ($versions->isEmpty())
                <x-ui::empty-state icon="palette" :title="__('content::custom_themes.versions.empty_title')" :description="__('content::custom_themes.versions.empty_description')">
                    <x-ui::button variant="secondary" icon="download" :href="vendor_route('store.custom-themes.starter-kit')">{{ __('content::custom_themes.starter_kit') }}</x-ui::button>
                </x-ui::empty-state>
            @else
                <div class="relative overflow-x-auto">
                    <table data-lead class="vnd-table w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="text-start text-sm text-ink-secondary">
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.versions.version') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.versions.status') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.versions.uploaded') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.versions.files') }}</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('content::custom_themes.versions.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($versions as $version)
                                @php $isLive = $theme->live_version_id === $version->id; @endphp
                                <tr data-version="{{ $version->id }}">
                                    <th scope="row" class="px-5 py-3 text-start font-medium text-ink">
                                        <a href="{{ vendor_route('store.custom-themes.show', ['version' => $version->id]) }}" class="hover:text-primary hover:underline">{{ $version->name }} {{ $version->version }}</a>
                                        @if ($isLive)
                                            <x-ui::badge tone="brand" class="ms-2">{{ __('content::custom_themes.versions.live') }}</x-ui::badge>
                                        @endif
                                    </th>
                                    <td class="px-5 py-3"><x-ui::badge :tone="$version->status->tone()">{{ $version->status->label() }}</x-ui::badge></td>
                                    <td class="px-5 py-3 text-ink-secondary">
                                        {{ $version->created_at?->isoFormat('D MMM YYYY, HH:mm') }}
                                        @if ($version->uploaded_by_name)
                                            <span class="block text-xs">{{ __('content::custom_themes.versions.by', ['name' => $version->uploaded_by_name]) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-ink-secondary">{{ $version->file_count }} · {{ \Illuminate\Support\Number::fileSize($version->size_bytes) }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-ui::button size="sm" variant="ghost" icon="file" :href="vendor_route('store.custom-themes.show', ['version' => $version->id])">{{ __('content::custom_themes.versions.report') }}</x-ui::button>
                                            @if ($version->status !== CustomThemeVersionStatus::Failed)
                                                <x-ui::button size="sm" variant="secondary" icon="eye" :href="vendor_route('store.custom-themes.preview', ['version' => $version->id])" target="_blank" rel="noopener">{{ __('content::custom_themes.actions.preview') }}</x-ui::button>
                                            @endif
                                            @if ($canManage && $version->status === CustomThemeVersionStatus::Approved && ! $isLive)
                                                <x-ui::button size="sm" icon="upload" :href="vendor_route('store.custom-themes.publish', ['version' => $version->id])" data-confirm="{{ __('content::custom_themes.actions.publish_confirm', ['version' => $version->version]) }}" data-confirm-title="{{ $theme->live_version_id && $version->id < $theme->live_version_id ? __('content::custom_themes.actions.rollback') : __('content::custom_themes.actions.publish') }}" data-confirm-tone="primary" data-confirm-method="POST">{{ $theme->live_version_id && $version->id < $theme->live_version_id ? __('content::custom_themes.actions.rollback') : __('content::custom_themes.actions.publish') }}</x-ui::button>
                                            @endif
                                            @if ($canManage && ! $isLive && $version->status !== CustomThemeVersionStatus::PendingReview)
                                                <x-ui::button size="sm" variant="ghost" icon="trash" :href="vendor_route('store.custom-themes.destroy', ['version' => $version->id])" data-confirm="{{ __('content::custom_themes.actions.delete_confirm', ['version' => $version->version]) }}" data-confirm-title="{{ __('content::custom_themes.actions.delete') }}" data-confirm-method="DELETE" :aria-label="__('content::custom_themes.actions.delete')">{{ __('content::custom_themes.actions.delete') }}</x-ui::button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui::card>
    </div>
</x-vendor::layout>
