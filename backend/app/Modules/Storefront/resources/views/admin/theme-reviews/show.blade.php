{{--
    Admin › Theme review queue › review detail (ADM-089a, Figma Admin Panel 78:81175):
    store + submission (note), validation report (problems / warnings), changes against the
    store's live / previous approved version (added / removed / changed files with a line
    diff, source links), theme.json summary, reviewer checklist, signed storefront preview
    and the approve / reject form (note required to reject; MAIL-034 to the store owners).
--}}
@php
    use App\Modules\Storefront\Enums\CustomThemeVersionStatus;

    $title = __('content::custom_themes.review.detail', ['name' => $version->name, 'version' => $version->version]);
    $errors_ = $version->errors();
    $warnings = $version->warnings();
    $manifest = $version->manifest ?? [];
    $source = fn (string $path) => route('admin.storefront.theme-reviews.file', ['version' => $version->id, 'path' => $path]);
    $textFile = fn (string $path) => preg_match('/\.(twig|json|css|js|svg|md|txt)$/i', $path) === 1;
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-theme-reviews">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :subtitle="(string) ($store?->name ?? '')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.theme_review_queue') => route('admin.storefront.theme-reviews.index'), $title => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" :href="route('admin.storefront.theme-reviews.index')">{{ __('content::custom_themes.review.title') }}</x-ui::button>
                <x-ui::button variant="secondary" icon="eye" :href="route('admin.storefront.theme-reviews.preview', $version->id)" target="_blank" rel="noopener">{{ __('content::custom_themes.review.preview') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="grid min-w-0 gap-6 xl:col-span-2">
                <x-ui::card :title="__('content::custom_themes.report.summary')" id="review-report">
                    <div class="grid gap-4 text-sm">
                        <div @class(['flex items-start gap-3 rounded-card px-4 py-3', 'bg-success-bg text-success' => $errors_ === [], 'bg-danger-bg text-danger' => $errors_ !== []])>
                            <x-ui::icon :name="$errors_ === [] ? 'check-circle' : 'x-circle'" :size="18" class="mt-0.5 shrink-0" />
                            <span>{{ $errors_ === [] ? __('content::custom_themes.report.passed') : __('content::custom_themes.report.failed') }}</span>
                        </div>
                        @foreach ($errors_ as $message)
                            <p class="flex items-start gap-2 font-mono text-[13px] text-ink"><x-ui::icon name="x-circle" :size="16" class="mt-0.5 shrink-0 text-danger" /><span class="min-w-0 break-words">{{ $message }}</span></p>
                        @endforeach
                        @forelse ($warnings as $message)
                            <p class="flex items-start gap-2 font-mono text-[13px] text-ink"><x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0 text-warning" /><span class="min-w-0 break-words">{{ $message }}</span></p>
                        @empty
                            <p class="text-ink-secondary">{{ __('content::custom_themes.report.no_warnings') }}</p>
                        @endforelse
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('content::custom_themes.review.changes')" id="review-changes">
                    <div class="grid gap-4 text-sm">
                        <p class="text-ink-secondary">{{ $diff['base'] ? __('content::custom_themes.review.compared_to', ['name' => $diff['base']->name, 'version' => $diff['base']->version]) : __('content::custom_themes.review.first_version') }}</p>
                        @if ($diff['base'])
                            <p class="text-ink-secondary">{{ __('content::custom_themes.review.unchanged', ['count' => $diff['unchanged']]) }}</p>
                        @endif
                        @foreach (['added' => 'success', 'removed' => 'danger'] as $kind => $tone)
                            @if ($diff[$kind] !== [])
                                <div class="grid gap-1">
                                    <h3 class="font-semibold text-ink">{{ __('content::custom_themes.review.'.$kind) }}</h3>
                                    <ul class="grid gap-1" dir="ltr">
                                        @foreach ($diff[$kind] as $path)
                                            <li class="flex min-w-0 items-center gap-2 font-mono text-[13px]">
                                                <x-ui::badge :tone="$tone">{{ __('content::custom_themes.review.'.$kind) }}</x-ui::badge>
                                                @if ($kind === 'added' && $textFile($path))
                                                    <a href="{{ $source($path) }}" target="_blank" rel="noopener" class="min-w-0 break-all text-ink hover:text-primary hover:underline" data-review-path>{{ $path }}</a>
                                                @else
                                                    <span class="min-w-0 break-all text-ink" data-review-path>{{ $path }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                        @foreach ($diff['changed'] as $path => $lines)
                            <details class="rounded-card border border-line" open>
                                <summary class="flex cursor-pointer flex-wrap items-center gap-2 px-3 py-2 font-mono text-[13px] text-ink" dir="ltr">
                                    <x-ui::badge tone="warning">{{ __('content::custom_themes.review.changed') }}</x-ui::badge>
                                    <span class="min-w-0 break-all" data-review-path>{{ $path }}</span>
                                    @if ($textFile($path))
                                        <a href="{{ $source($path) }}" target="_blank" rel="noopener" class="ms-auto shrink-0 text-xs text-primary hover:underline">{{ __('content::custom_themes.review.view_source') }}</a>
                                    @endif
                                </summary>
                                @if ($lines === null)
                                    <p class="px-3 pb-3 text-ink-secondary">{{ $textFile($path) ? __('content::custom_themes.review.too_large') : __('content::custom_themes.review.binary_changed') }}</p>
                                @else
                                    <pre class="overflow-x-auto border-t border-line text-[12px] leading-5" dir="ltr"><code>@foreach ($lines as $line)<span @class(['block px-3', 'bg-success-bg text-success' => $line['type'] === 'add', 'bg-danger-bg text-danger' => $line['type'] === 'del', 'text-ink-secondary' => $line['type'] === 'gap'])>{{ ['add' => '+ ', 'del' => '- ', 'same' => '  ', 'gap' => ''][$line['type']] }}{{ $line['text'] }}</span>@endforeach</code></pre>
                                @endif
                            </details>
                        @endforeach
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('content::custom_themes.report.files')" :padding="false" id="review-files">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm" dir="ltr">
                            <thead>
                                <tr class="bg-subtle text-start text-ink-secondary">
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.report.file') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.report.size') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::custom_themes.report.checksum') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($version->files() as $path => $file)
                                    <tr class="border-t border-line">
                                        <td class="break-all px-5 py-2 font-mono text-[13px]">
                                            @if ($textFile($path))
                                                <a href="{{ $source($path) }}" target="_blank" rel="noopener" class="text-ink hover:text-primary hover:underline">{{ $path }}</a>
                                            @else
                                                <span class="text-ink">{{ $path }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2 text-ink-secondary">{{ \Illuminate\Support\Number::fileSize((int) $file['size']) }}</td>
                                        <td class="px-5 py-2 font-mono text-xs text-ink-secondary">{{ substr((string) $file['sha256'], 0, 12) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui::card>
            </div>

            <div class="grid min-w-0 content-start gap-6">
                <x-ui::card :title="__('content::custom_themes.review.decision')" id="review-decision">
                    <div class="grid gap-4 text-sm text-ink">
                        <x-ui::badge :tone="$version->status->tone()">{{ $version->status->label() }}</x-ui::badge>
                        <p class="text-ink-secondary">{{ __('content::custom_themes.review.submitted_at', ['date' => $version->submitted_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—', 'name' => $version->uploaded_by_name ?? '—']) }}</p>
                        @if ($version->submit_note)
                            <div class="grid gap-1">
                                <span class="text-xs text-ink-secondary">{{ __('content::custom_themes.review.submit_note') }}</span>
                                <p class="whitespace-pre-line rounded-card bg-subtle px-3 py-2">{{ $version->submit_note }}</p>
                            </div>
                        @endif
                        @if ($version->status === CustomThemeVersionStatus::PendingReview)
                            @if ($canManage)
                                <form method="POST" action="{{ route('admin.storefront.theme-reviews.decide', $version->id) }}" data-ajax novalidate class="grid gap-3" id="theme-review-form">
                                    @csrf
                                    <x-ui::textarea name="note" :label="__('content::custom_themes.review.note')" :helper="__('content::custom_themes.review.note_hint')" maxlength="2000" :rows="4" />
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui::button type="submit" name="decision" value="approve" icon="check">{{ __('content::custom_themes.review.approve') }}</x-ui::button>
                                        <x-ui::button type="submit" name="decision" value="reject" variant="danger" icon="x">{{ __('content::custom_themes.review.reject') }}</x-ui::button>
                                    </div>
                                </form>
                            @endif
                        @else
                            <p class="text-ink-secondary">{{ __('content::custom_themes.review.decided', ['status' => mb_strtolower($version->status->label()), 'name' => $version->reviewed_by_name ?? '—', 'date' => $version->reviewed_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—']) }}</p>
                            @if ($version->review_note)
                                <p class="whitespace-pre-line rounded-card bg-subtle px-3 py-2">{{ $version->review_note }}</p>
                            @endif
                        @endif
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('content::custom_themes.review.checklist')">
                    <ul class="grid gap-2 text-sm text-ink">
                        @foreach (trans('content::custom_themes.review.checklist_items') as $item)
                            <li class="flex items-start gap-2"><x-ui::icon name="shield" :size="16" class="mt-0.5 shrink-0 text-ink-secondary" /><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </x-ui::card>

                @if ($manifest !== [])
                    <x-ui::card :title="__('content::custom_themes.report.manifest')">
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.review.store') }}</dt><dd class="text-ink">{{ $store?->name }}</dd></div>
                            <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.author') }}</dt><dd class="text-ink">{{ $manifest['author']['name'] ?? '—' }}{{ ! empty($manifest['author']['email']) ? ' · '.$manifest['author']['email'] : '' }}</dd></div>
                            @if (! empty($manifest['description']))
                                <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.description') }}</dt><dd class="text-ink">{{ $manifest['description'] }}</dd></div>
                            @endif
                            <div><dt class="text-xs text-ink-secondary">{{ __('content::custom_themes.report.settings_count') }}</dt><dd class="text-ink">{{ count((array) ($manifest['settings'] ?? [])) }}</dd></div>
                        </dl>
                    </x-ui::card>
                @endif
            </div>
        </div>
    </div>
</x-layouts.panel>
