{{--
    ADM-092a "Translate with AI" modal body (Admin Panel 79:91040): scope "This file" /
    "All missing keys in <language>", estimated words and locked keys (skipped) per
    scope; the submit label counts the keys of the chosen scope. No cost row: provider
    prices are not configured (decision #100 e). Loaded into #ai-keys-modal.
    Data: $file, $locale, $language, $estimates {file|all: {keys, words, locked}}.
--}}
@php
    $numberLocale = \App\Shared\Support\Locale::numberLocale();
    $format = fn (int $value): string => \Illuminate\Support\Number::format($value, locale: $numberLocale);
    $scope = $estimates['file']['keys'] > 0 ? 'file' : 'all';
    $labels = array_map(fn (array $estimate): string => trans_choice('ai::admin_translation.keys.submit', $estimate['keys'], ['count' => $format($estimate['keys'])]), $estimates);
@endphp
<form id="ai-keys-form" method="POST" action="{{ route('admin.localization.ai-translation.static-keys.store') }}" data-ajax novalidate class="grid gap-5" data-ai-keys-form data-modal-title="{{ __('ai::admin_translation.keys.title') }}" data-modal-subtitle="{{ $file->label() }}" data-submit-label="{{ $labels[$scope] }}" @if ($estimates[$scope]['keys'] === 0) data-submit-disabled @endif>
    @csrf
    <input type="hidden" name="locale" value="{{ $locale }}">
    <input type="hidden" name="file" value="{{ $file->id }}">
    <fieldset class="grid gap-3">
        <legend class="mb-1 text-sm font-medium text-ink">{{ __('ai::admin_translation.keys.scope') }}</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-3">
            <x-ui::radio name="scope" value="file" :label="__('ai::admin_translation.keys.scope_file')" :checked="$scope === 'file'" :disabled="$estimates['file']['keys'] === 0" data-ai-keys-scope />
            <x-ui::radio name="scope" value="all" :label="__('ai::admin_translation.keys.scope_all', ['language' => $language])" :checked="$scope === 'all'" data-ai-keys-scope />
        </div>
    </fieldset>
    @foreach ($estimates as $key => $estimate)
        <dl class="grid gap-2.5 text-sm" data-ai-keys-estimate="{{ $key }}" data-submit-label="{{ $labels[$key] }}" data-keys="{{ $estimate['keys'] }}" @if ($key !== $scope) hidden @endif>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-ink-secondary">{{ __('ai::admin_translation.keys.missing') }}</dt>
                <dd class="font-semibold text-ink"><bdi>{{ $format($estimate['keys']) }}</bdi></dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-ink-secondary">{{ __('ai::admin_translation.keys.words') }}</dt>
                <dd class="font-semibold text-ink"><bdi>{{ $format($estimate['words']) }}</bdi></dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-ink-secondary">{{ __('ai::admin_translation.keys.locked') }}</dt>
                <dd class="font-semibold text-ink"><bdi>{{ __('ai::admin_translation.keys.skipped', ['count' => $format($estimate['locked'])]) }}</bdi></dd>
            </div>
        </dl>
    @endforeach
    @if ($estimates['all']['keys'] === 0)
        <p class="rounded-control bg-subtle p-3 text-sm text-ink-secondary" role="status">{{ __('ai::admin_translation.keys.none', ['language' => $language]) }}</p>
    @endif
    <p class="text-xs text-ink-secondary">{{ __('ai::admin_translation.keys.hint') }}</p>
</form>
