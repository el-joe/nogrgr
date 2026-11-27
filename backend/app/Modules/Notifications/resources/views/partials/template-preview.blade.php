{{--
    Live preview of the e-mail template editor: the unsaved subject / body rendered on the
    server through the real MAIL-001 layout with sample values (POST to the form's
    data-preview-url, email-templates.js), shown in a sandboxed iframe; desktop / mobile.
    @param string|null $lastEdited  "last edited by … at …" line
--}}
<x-ui::card class="xl:sticky xl:top-20">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="ui-card-title text-base font-semibold text-ink">{{ __('notifications::templates.preview') }}</h2>
        <div class="inline-flex gap-1 rounded-control bg-subtle p-1" role="group" aria-label="{{ __('notifications::templates.preview_width') }}">
            @foreach (['desktop', 'mobile'] as $width)
                <button type="button" data-preview-width="{{ $width }}" aria-pressed="{{ $width === 'desktop' ? 'true' : 'false' }}" class="inline-flex h-7 items-center rounded-chip px-2.5 text-xs text-ink-secondary aria-pressed:bg-surface aria-pressed:font-medium aria-pressed:text-ink-brand aria-pressed:shadow-card">{{ __('notifications::templates.widths.'.$width) }}</button>
            @endforeach
        </div>
    </div>
    <div class="rounded-card bg-subtle p-3">
        <p class="mb-2 truncate text-xs text-ink-secondary"><span class="font-medium text-ink">{{ __('notifications::templates.fields.subject') }}:</span> <span data-preview-subject dir="auto"></span></p>
        <div class="mx-auto w-full transition-[max-width]" data-preview-pane>
            <iframe title="{{ __('notifications::templates.preview') }}" sandbox data-preview-frame class="h-[560px] w-full rounded-control border border-line bg-white"></iframe>
        </div>
        <p class="mt-2 hidden text-xs text-danger" data-preview-error role="alert">{{ __('notifications::templates.preview_failed') }}</p>
    </div>
    <p class="mt-3 text-xs text-ink-secondary">{{ __('notifications::templates.preview_help') }}</p>
    @if (filled($lastEdited ?? null))
        <p class="mt-1 text-xs text-ink-secondary" data-last-edited>{{ $lastEdited }}</p>
    @endif
</x-ui::card>
