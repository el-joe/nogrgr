{{--
    Setup progress widget (VEN-008, Vendor Panel new screens 82:100227 "Setup progress"):
    completed / total steps, progress bar and "Continue setup" (next open step, or the
    checklist). Used on the dashboard and the onboarding page.
    <x-vendor::setup-progress :progress="$progress" />
--}}
@props(['progress', 'continueUrl' => null])
@php
    /** @var \App\Modules\Platform\DTOs\SetupProgressData $progress */
    $continueUrl ??= vendor_route('onboarding', ['tab' => 'checklist']);
    $next = $progress->next();
@endphp
<section {{ $attributes->class(['rounded-card border border-line bg-surface p-5 shadow-card']) }} aria-labelledby="setup-progress-title" data-reveal="fade-up">
    <h2 id="setup-progress-title" class="text-base font-semibold text-ink">{{ __('platform::onboarding.widget.title') }}</h2>
    <div class="mt-5 flex items-center justify-between gap-3 text-sm">
        <span class="font-medium text-ink">{{ __('platform::onboarding.widget.store_setup') }}</span>
        <span class="text-xs text-ink-secondary" dir="ltr">{{ __('platform::onboarding.widget.steps', ['done' => $progress->completed, 'total' => $progress->total]) }}</span>
    </div>
    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress->percent() }}" aria-label="{{ __('platform::onboarding.widget.percent', ['percent' => $progress->percent()]) }}">
        <span class="vnd-progress-fill block h-full rounded-full bg-primary" style="--vnd-progress: {{ $progress->percent() }}%"></span>
    </div>
    @if ($progress->isComplete())
        <p class="mt-4 flex items-center gap-2 text-sm text-success"><x-ui::icon name="check-circle" :size="18" />{{ __('platform::onboarding.widget.complete') }}</p>
    @else
        @if ($next)
            <p class="mt-4 text-[13px] text-ink-secondary">{{ __('platform::onboarding.widget.next', ['step' => $next->title]) }}</p>
        @endif
        <x-ui::button :href="$continueUrl" class="mt-4">{{ __('platform::onboarding.widget.continue') }}</x-ui::button>
    @endif
</section>
