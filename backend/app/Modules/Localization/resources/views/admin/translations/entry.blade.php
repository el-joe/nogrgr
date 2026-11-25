{{--
    Read-only view of one key in every editor language (loaded into #translation-modal).
--}}
@php
    /** @var \App\Modules\Localization\Support\TranslationFile $file */
@endphp
<div class="grid gap-5" data-modal-title="{{ __('localization::translations.view_title', ['key' => $entry['key']]) }}" data-modal-subtitle="{{ $file->label() }}">
    @if ($entry['locked'])
        <div class="flex items-start gap-2 rounded-control bg-danger-bg p-3 text-sm text-ink" role="note">
            <x-ui::icon name="shield" :size="18" class="mt-0.5 shrink-0 text-danger" />
            <p>{{ __('localization::translations.hints.locked') }}</p>
        </div>
    @endif
    <dl class="grid gap-4 text-sm">
        <div class="grid gap-1">
            <dt class="text-ink-secondary">{{ __('localization::translations.fields.key') }}</dt>
            <dd class="font-medium text-ink" dir="ltr">{{ $entry['key'] }}</dd>
        </div>
        @foreach ($locales as $code => $name)
            <div class="grid gap-1">
                <dt class="text-ink-secondary">{{ $name }} <span class="text-ink-muted" dir="ltr">({{ $code }})</span></dt>
                <dd class="whitespace-pre-line text-ink" dir="{{ \App\Shared\Support\Locale::direction($code) }}" lang="{{ $code }}">
                    @if (($entry['values'][$code] ?? null) === null || $entry['values'][$code] === '')
                        <x-ui::badge tone="warning">{{ __('localization::translations.missing_value') }}</x-ui::badge>
                    @else
                        {{ $entry['values'][$code] }}
                        @if ($entry['values'][$code] !== ($entry['disk'][$code] ?? null))
                            <x-ui::badge tone="info" class="ms-1">{{ __('localization::translations.edited') }}</x-ui::badge>
                        @endif
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</div>
