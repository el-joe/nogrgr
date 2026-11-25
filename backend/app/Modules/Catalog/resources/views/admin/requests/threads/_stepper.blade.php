{{-- Manufacturing pipeline (Figma 68:33125): done steps checked, current step numbered in brand. --}}
@use('App\Modules\Catalog\Enums\ManufacturingRequestStatus')
@php
    $steps = ManufacturingRequestStatus::steps();
    $currentIndex = array_search($status, $steps, true);
@endphp
@if ($currentIndex === false)
    <p class="flex items-center gap-2 rounded-card border border-line bg-danger-bg px-4 py-3 text-sm text-danger" role="status">
        <x-ui::icon name="x-circle" :size="18" />
        {{ __('catalog::requests.stopped', ['status' => $status->label()]) }}
    </p>
@else
    <ol class="flex items-center gap-2 overflow-x-auto pb-1" aria-label="{{ __('catalog::requests.steps.label') }}">
        @foreach ($steps as $index => $step)
            @php($done = $index < $currentIndex)
            @php($current = $index === $currentIndex)
            <li class="flex min-w-max flex-1 items-center gap-2" @if ($current) aria-current="step" @endif>
                <span @class([
                    'grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold',
                    'bg-success text-on-primary' => $done,
                    'bg-primary text-on-primary' => $current,
                    'border border-line-strong text-ink-secondary' => ! $done && ! $current,
                ])>
                    @if ($done)
                        <x-ui::icon name="check" :size="14" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span @class(['text-sm', 'font-medium text-ink' => $done || $current, 'text-ink-secondary' => ! $done && ! $current])>{{ $step->label() }}</span>
                @unless ($loop->last)
                    <span @class(['h-0.5 min-w-6 flex-1 rounded-full', 'bg-success' => $done, 'bg-line' => ! $done]) aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>
@endif
