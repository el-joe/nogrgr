{{--
    Vendor › Custom theme › one version (VEN-036 validation report): result banner,
    problems / warnings, theme.json summary, platform review (state, reviewer note), the
    submit-for-review form (note) or withdraw, preview / publish / delete, and the file list.
--}}
@php
    use App\Modules\Storefront\Enums\CustomThemeVersionStatus;

    $errors_ = $version->errors();
    $warnings = $version->warnings();
    $manifest = $version->manifest ?? [];
    $isLive = $theme->live_version_id === $version->id;
    $title = __('content::custom_themes.report.title', ['name' => $version->name, 'version' => $version->version]);
@endphp
<x-vendor::layout :title="$title" page="vendor-custom-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="$title"
            :subtitle="__('content::custom_themes.report.summary')"
            :breadcrumbs="[__('content::custom_themes.crumb') => null, __('content::custom_themes.title') => vendor_route('store.custom-themes.index'), $title => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" :href="vendor_route('store.custom-themes.index')">{{ __('content::custom_themes.actions.back') }}</x-ui::button>
                @if ($version->status !== CustomThemeVersionStatus::Failed)
                    <x-ui::button variant="secondary" icon="eye" :href="vendor_route('store.custom-themes.preview', ['version' => $version->id])" target="_blank" rel="noopener">{{ __('content::custom_themes.actions.preview') }}</x-ui::button>
                @endif
                @if ($canManage && $version->status === CustomThemeVersionStatus::Approved && ! $isLive)
                    <x-ui::button icon="upload" :href="vendor_route('store.custom-themes.publish', ['version' => $version->id])" data-confirm="{{ __('content::custom_themes.actions.publish_confirm', ['version' => $version->version]) }}" data-confirm-title="{{ __('content::custom_themes.actions.publish') }}" data-confirm-tone="primary" data-confirm-method="POST">{{ __('content::custom_themes.actions.publish') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div @class(['flex items-start gap-3 rounded-card px-4 py-3 text-sm', 'bg-success-bg text-success' => $errors_ === [], 'bg-danger-bg text-danger' => $errors_ !== []]) role="status" data-report-result="{{ $errors_ === [] ? 'passed' : 'failed' }}">
            <x-ui::icon :name="$errors_ === [] ? 'check-circle' : 'x-circle'" :size="18" class="mt-0.5 shrink-0" />
            <span>{{ $errors_ === [] ? __('content::custom_themes.report.passed') : __('content::custom_themes.report.failed') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="grid min-w-0 gap-6 xl:col-span-2">
                @if ($errors_ !== [])
                    <x-ui::card :title="__('content::custom_themes.report.errors')" id="custom-theme-errors">
                        <ul class="grid gap-2 text-sm text-ink">
                            @foreach ($errors_ as $message)
                                <li class="flex items-start gap-2"><x-ui::icon name="x-circle" :size="16" class="mt-0.5 shrink-0 text-danger" /><span class="min-w-0 break-words font-mono text-[13px]">{{ $message }}</span></li>
                            @endforeach
                        </ul>
                    </x-ui::card>
                @endif

                <x-ui::card :title="__('content::custom_themes.report.warnings')" id="custom-theme-warnings">
                    @if ($warnings === [])
                        <p class="text-sm text-ink-secondary">{{ __('content::custom_themes.report.no_warnings') }}</p>
                    @else
                        <ul class="grid gap-2 text-sm text-ink">
                            @foreach ($warnings as $message)
                                <li class="flex items-start gap-2"><x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0 text-warning" /><span class="min-w-0 break-words font-mono text-[13px]">{{ $message }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui::card>

                <x-ui::card :title="__('content::custom_themes.report.files')" :padding="false" id="custom-theme-files">
                    <div class="relative overflow-x-auto">
                        <table class="vnd-table w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="text-start text-sm text-ink-secondary">
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.report.file') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.report.size') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.report.checksum') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($version->files() as $path => $file)
                                    <tr>
                                        <td class="break-all px-5 py-2 font-mono text-[13px] text-ink" dir="ltr">{{ $path }}</td>
                                        <td class="px-5 py-2 text-ink-secondary">{{ \Illuminate\Support\Number::fileSize((int) $file['size']) }}</td>
                                        <td class="px-5 py-2 font-mono text-xs text-ink-secondary" dir="ltr">{{ substr((string) $file['sha256'], 0, 12) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-4 text-ink-secondary">{{ __('content::custom_themes.report.failed') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui::card>
            </div>

            <div class="grid min-w-0 content-start gap-6">
                <x-ui::card :title="__('content::custom_themes.report.review')" id="custom-theme-review">
                    <div class="grid gap-4 text-sm text-ink">
                        <x-ui::badge :tone="$version->status->tone()">{{ $version->status->label() }}</x-ui::badge>
                        @if ($version->status === CustomThemeVersionStatus::PendingReview)
                            <p class="text-ink-secondary">{{ __('content::custom_themes.report.pending', ['date' => $version->submitted_at?->isoFormat('D MMM YYYY, HH:mm')]) }}</p>
                            @if ($canManage)
                                <div><x-ui::button size="sm" variant="secondary" :href="vendor_route('store.custom-themes.withdraw', ['version' => $version->id])" data-confirm="{{ __('content::custom_themes.actions.withdraw_confirm') }}" data-confirm-title="{{ __('content::custom_themes.actions.withdraw') }}" data-confirm-tone="warning" data-confirm-method="POST">{{ __('content::custom_themes.actions.withdraw') }}</x-ui::button></div>
                            @endif
                        @endif
                        @if ($version->reviewed_at)
                            <p class="text-ink-secondary">{{ __('content::custom_themes.report.reviewed_by', ['name' => $version->reviewed_by_name, 'date' => $version->reviewed_at->isoFormat('D MMM YYYY, HH:mm')]) }}</p>
                            @if ($version->review_note)
                                <div class="grid gap-1">
                                    <span class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.review_note') }}</span>
                                    <p class="whitespace-pre-line rounded-card bg-subtle px-3 py-2" data-review-note>{{ $version->review_note }}</p>
                                </div>
                            @endif
                        @endif
                        @if ($version->status->canSubmit())
                            <p class="text-ink-secondary">{{ __('content::custom_themes.report.not_submitted') }}</p>
                            @if ($canManage)
                                <form method="POST" action="{{ vendor_route('store.custom-themes.submit', ['version' => $version->id]) }}" data-ajax novalidate class="grid gap-3" id="custom-theme-submit-form">
                                    @csrf
                                    <x-ui::textarea name="note" :label="__('content::custom_themes.report.submit_note')" :helper="__('content::custom_themes.report.submit_note_hint')" maxlength="1000" :rows="3" />
                                    <div><x-ui::button type="submit" icon="check">{{ __('content::custom_themes.actions.submit') }}</x-ui::button></div>
                                </form>
                            @endif
                        @endif
                    </div>
                </x-ui::card>

                @if ($manifest !== [])
                    <x-ui::card :title="__('content::custom_themes.report.manifest')" id="custom-theme-manifest">
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.author') }}</dt><dd class="text-ink">{{ $manifest['author']['name'] ?? '—' }}</dd></div>
                            @if (! empty($manifest['description']))
                                <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.description') }}</dt><dd class="text-ink">{{ $manifest['description'] }}</dd></div>
                            @endif
                            <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.supports') }}</dt><dd class="text-ink">{{ implode(', ', array_map(fn ($page) => \App\Modules\Storefront\Enums\CustomThemeTemplate::tryFrom($page)?->label() ?? $page, (array) ($manifest['supports'] ?? []))) }}</dd></div>
                            <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.settings_count') }}</dt><dd class="text-ink">{{ count((array) ($manifest['settings'] ?? [])) }}</dd></div>
                        </dl>
                    </x-ui::card>
                @endif

                @if ($canManage && ! $isLive && $version->status !== CustomThemeVersionStatus::PendingReview)
                    <div>
                        <x-ui::button variant="danger" icon="trash" :href="vendor_route('store.custom-themes.destroy', ['version' => $version->id])" data-confirm="{{ __('content::custom_themes.actions.delete_confirm', ['version' => $version->version]) }}" data-confirm-title="{{ __('content::custom_themes.actions.delete') }}" data-confirm-method="DELETE">{{ __('content::custom_themes.actions.delete') }}</x-ui::button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-vendor::layout>
