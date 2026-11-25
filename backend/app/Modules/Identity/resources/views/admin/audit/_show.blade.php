{{--
    Audit event detail (ADM-112a, Figma Admin Panel 80:98485): who / when / where, then
    each changed field as "− before" (danger) and "+ after" (success) side by side,
    then the request context (method, status, parameters, input keys).
--}}
@php
    /** @var \App\Modules\Platform\Models\AuditEvent $event */
    $title = \App\Modules\Identity\Support\AuditPresenter::actionLabel($event->action);
    $subtitle = collect([$actor, $time?->isoFormat('D MMM YYYY, HH:mm'), $event->ip_address])->filter()->implode(' · ');
@endphp
<div class="grid gap-6" data-modal-title="{{ $title }}" data-modal-subtitle="{{ $subtitle }}">
    <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
        <div class="grid gap-0.5">
            <dt class="text-xs text-ink-secondary">{{ __('identity::audit.meta.subject') }}</dt>
            <dd class="text-ink">{{ \App\Modules\Identity\Support\AuditPresenter::subjectLabel($event->subject_type) }}@if ($event->subject_id) <bdi dir="ltr">#{{ $event->subject_id }}</bdi>@endif</dd>
        </div>
        <div class="grid gap-0.5">
            <dt class="text-xs text-ink-secondary">{{ __('identity::audit.meta.tenant') }}</dt>
            <dd class="text-ink">{{ $tenant ?? __('identity::audit.platform') }}</dd>
        </div>
        <div class="grid gap-0.5">
            <dt class="text-xs text-ink-secondary">{{ __('identity::audit.meta.guard') }}</dt>
            <dd class="text-ink">{{ $event->guard ? __('identity::audit.guards.'.$event->guard) : __('identity::audit.system_actor') }}</dd>
        </div>
        <div class="grid gap-0.5">
            <dt class="text-xs text-ink-secondary">{{ __('identity::audit.columns.action') }}</dt>
            <dd><bdi dir="ltr" class="text-ink">{{ $event->action }}</bdi></dd>
        </div>
        @if ($event->url)
            <div class="grid gap-0.5 sm:col-span-2">
                <dt class="text-xs text-ink-secondary">{{ __('identity::audit.meta.url') }}</dt>
                <dd class="break-all"><bdi dir="ltr" class="text-ink">{{ $event->url }}</bdi></dd>
            </div>
        @endif
        @if ($event->user_agent)
            <div class="grid gap-0.5 sm:col-span-2">
                <dt class="text-xs text-ink-secondary">{{ __('identity::audit.meta.user_agent') }}</dt>
                <dd class="break-all text-ink-secondary"><bdi dir="ltr">{{ $event->user_agent }}</bdi></dd>
            </div>
        @endif
    </dl>

    @if (count($diff) > 0)
        <div class="grid gap-4">
            @foreach ($diff as $row)
                <section class="grid gap-2" aria-label="{{ $row['field'] }}">
                    <h3 class="text-sm font-medium text-ink"><bdi dir="ltr">{{ $row['field'] }}</bdi></h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="rounded-control border-s-2 border-danger bg-danger-bg px-3 py-2">
                            <p class="text-xs font-medium text-danger">− {{ __('identity::audit.before') }}</p>
                            <p class="mt-1 break-all text-[13px] text-ink"><bdi dir="ltr">{{ $row['before'] ?? '—' }}</bdi></p>
                        </div>
                        <div class="rounded-control border-s-2 border-success bg-success-bg px-3 py-2">
                            <p class="text-xs font-medium text-success">+ {{ __('identity::audit.after') }}</p>
                            <p class="mt-1 break-all text-[13px] text-ink"><bdi dir="ltr">{{ $row['after'] ?? '—' }}</bdi></p>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @elseif (count($context) === 0)
        <p class="rounded-control bg-subtle px-4 py-3 text-[13px] text-ink-secondary">{{ __('identity::audit.no_diff') }}</p>
    @endif

    @if (count($context) > 0)
        <section class="grid gap-2">
            <h3 class="text-sm font-medium text-ink">{{ __('identity::audit.context') }}</h3>
            <dl class="grid gap-2 rounded-control bg-subtle p-4 text-[13px]">
                @foreach ($context as $key => $value)
                    <div class="grid gap-0.5 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <dt class="text-ink-secondary"><bdi dir="ltr">{{ $key }}</bdi></dt>
                        <dd class="break-all text-ink"><bdi dir="ltr">{{ $value }}</bdi></dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
</div>
