{{--
    Variable picker of the e-mail template editor (decision #118): every documented
    variable of the event with its description and sample value; a click inserts
    the variable token at the cursor (email-templates.js, data-insert-variable).
    @param \App\Modules\Notifications\Enums\EmailTemplateEvent $event
--}}
<div class="grid gap-2">
    <span class="text-xs font-medium text-ink-secondary">{{ __('notifications::templates.variables_help') }}</span>
    <ul class="grid gap-1.5 sm:grid-cols-2" data-variable-chips>
        @foreach ($event->variableDefinitions() as $name => $variable)
            @php $token = '{'.'{ '.$name.' }'.'}'; @endphp
            <li>
                <button type="button" data-insert-variable="{{ $name }}" class="grid w-full gap-0.5 rounded-control border border-line bg-surface px-3 py-2 text-start hover:border-primary focus-visible:border-primary">
                    <code class="text-xs font-medium text-ink-brand" dir="ltr">{{ $token }}</code>
                    <span class="text-xs text-ink-secondary">{{ $variable['description'] }}</span>
                    @if ($variable['sample'] !== '')
                        <span class="truncate text-[11px] text-ink-muted" dir="auto">{{ __('notifications::templates.sample', ['value' => $variable['sample']]) }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
</div>
