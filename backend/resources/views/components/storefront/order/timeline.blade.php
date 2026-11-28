{{--
    Tracking timeline (Figma 100:102399): newest step first, green dot when delivered,
    red dots otherwise, grey for upcoming steps, 2px connector. $steps from AccountOrderPages::detail()['tracking'].
--}}
@props(['steps' => []])
<ol {{ $attributes->class(['grid gap-3.5']) }} data-reveal-stagger>
    @foreach ($steps as $step)
        <li class="flex items-start gap-3" data-reveal="fade-up">
            <span class="relative flex w-3 shrink-0 flex-col items-center self-stretch pt-1" aria-hidden="true">
                <span @class(['size-3 rounded-full', 'bg-sf-border' => $step['upcoming'] ?? false, 'bg-sf-success' => $step['done'], 'bg-sf-accent' => ! $step['done'] && ! ($step['upcoming'] ?? false)])></span>
                @unless ($loop->last)<span class="-mb-3.5 w-0.5 flex-1 rounded-full bg-sf-border"></span>@endunless
            </span>
            <span class="grid gap-0">
                <span @class(['text-sm font-medium leading-5', 'text-sf-ink' => ! ($step['upcoming'] ?? false), 'text-sf-muted' => $step['upcoming'] ?? false])>{{ $step['label'] === '__placed' ? __st('orders.tracking_placed') : $step['label'] }}</span>
                <span class="text-xs leading-4 text-sf-muted">{{ ($step['upcoming'] ?? false) ? __st('orders.timeline.expected') : collect([$step['at']?->translatedFormat('M j, H:i'), $step['note']])->filter()->implode(' · ') }}</span>
            </span>
        </li>
    @endforeach
</ol>
