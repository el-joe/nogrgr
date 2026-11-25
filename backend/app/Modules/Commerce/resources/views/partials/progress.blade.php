{{-- Progress steps of an order / return header: $steps = [[label, meta|null]], $current = 1-based step reached, $failed = bool. --}}
<ol class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-card border border-line bg-surface px-4 py-3 shadow-card sm:flex-nowrap">
    @foreach ($steps as $index => [$label, $meta])
        @php $n = $index + 1; $done = $n < $current || ($n === $current && ($complete ?? false)); $now = $n === $current && ! ($complete ?? false); @endphp
        <li class="flex min-w-0 flex-1 items-center gap-2.5">
            <span @class([
                'grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold',
                'bg-success text-white' => $done,
                'bg-primary text-on-primary' => $now && ! ($failed ?? false),
                'bg-danger text-white' => $now && ($failed ?? false),
                'border border-line-strong text-ink-secondary' => ! $done && ! $now,
            ]) @if ($now) aria-current="step" @endif>
                @if ($done)
                    <x-ui::icon name="check" :size="14" />
                @else
                    {{ $n }}
                @endif
            </span>
            <span class="min-w-0">
                <span @class(['block truncate text-sm', 'font-semibold text-ink' => $done || $now, 'text-ink-secondary' => ! $done && ! $now])>{{ $label }}</span>
                @if ($meta)
                    <span class="block truncate text-xs text-ink-secondary">{{ $meta }}</span>
                @endif
            </span>
            @unless ($loop->last)
                <span @class(['hidden h-0.5 flex-1 rounded-full sm:block', 'bg-success' => $done, 'bg-line' => ! $done]) aria-hidden="true"></span>
            @endunless
        </li>
    @endforeach
</ol>
