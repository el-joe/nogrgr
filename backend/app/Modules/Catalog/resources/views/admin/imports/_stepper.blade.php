{{--
    Import stepper (Figma 65:22120): Choose source → Map categories → Preview → Import.
    A step is done when its condition holds; the first open step is current.
    @param array<string, bool> $steps  key => done
--}}
@php
    $current = collect($steps)->search(fn (bool $done) => ! $done) ?: array_key_last($steps);
    $number = 0;
@endphp
<nav aria-label="{{ __('catalog::imports.steps.label') }}">
    <ol class="flex flex-wrap items-center gap-x-3 gap-y-2">
        @foreach ($steps as $key => $done)
            @php($number++)
            <li class="flex min-w-0 flex-1 items-center gap-3 last:flex-none" @if ($key === $current && ! $done) aria-current="step" @endif>
                <span @class([
                    'grid size-7 shrink-0 place-items-center rounded-full text-[13px] font-semibold',
                    'bg-success text-white' => $done,
                    'bg-primary text-on-primary' => ! $done && $key === $current,
                    'border border-line bg-surface text-ink-secondary' => ! $done && $key !== $current,
                ])>
                    @if ($done)
                        <x-ui::icon name="check" :size="16" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="whitespace-nowrap text-sm font-medium text-ink">{{ __('catalog::imports.steps.'.$key) }}</span>
                @unless ($loop->last)
                    <span @class(['hidden h-0.5 min-w-6 flex-1 rounded-full sm:block', 'bg-success' => $done, 'bg-line' => ! $done]) aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
