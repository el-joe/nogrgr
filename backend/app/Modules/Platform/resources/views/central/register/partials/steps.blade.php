{{--
    Register steps (Figma "Register steps" 125:11306): Account · Plan · Payment · Store.
    Input: $current (1-based) and optional $done (step keys already finished; defaults
    to the steps before $current). The account step is finished last (verify + complete
    come after plan / payment), so those pages pass $done explicitly. Finished steps
    show a check; on phones only the current step keeps its label (Figma 125:11865).
--}}
@php
    $stepKeys = ['account', 'plan', 'payment', 'store'];
    $doneKeys = $done ?? array_slice($stepKeys, 0, max(0, $current - 1));
@endphp
<ol class="flex items-center gap-2 sm:gap-3" aria-label="{{ __('platform::register.steps.label') }}" data-reveal="fade-up">
    @foreach ($stepKeys as $index => $key)
        @php
            $number = $index + 1;
            $isCurrent = $number === $current;
            $isDone = ! $isCurrent && in_array($key, $doneKeys, true);
        @endphp
        @if ($index > 0)
            <li aria-hidden="true" @class(['h-0.5 w-4 shrink-0 sm:w-10', 'bg-primary' => $isDone || $isCurrent, 'bg-(--reg-rail)' => ! $isDone && ! $isCurrent])></li>
        @endif
        <li class="flex items-center gap-2" @if ($isCurrent) aria-current="step" @endif>
            <span @class([
                'grid size-7 shrink-0 place-items-center rounded-[14px] text-[13px] leading-[18px]',
                'bg-primary font-semibold text-white' => $isDone || $isCurrent,
                'border border-(--reg-border) bg-white font-medium text-(--reg-muted)' => ! $isDone && ! $isCurrent,
            ])>
                @if ($isDone)
                    <x-ui::icon name="check" :size="14" />
                @else
                    {{ $number }}
                @endif
            </span>
            <span @class([
                'text-sm leading-5 whitespace-nowrap',
                'font-medium text-ink' => $isDone || $isCurrent,
                'text-(--reg-muted)' => ! $isDone && ! $isCurrent,
                'max-sm:sr-only' => ! $isCurrent,
            ])>
                {{ __('platform::register.steps.'.$key) }}
                @if ($isDone)<span class="sr-only">({{ __('platform::register.steps.done') }})</span>@endif
            </span>
        </li>
    @endforeach
</ol>
