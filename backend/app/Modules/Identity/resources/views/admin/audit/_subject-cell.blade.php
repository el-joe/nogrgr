<span class="grid">
    <span class="font-medium text-ink">
        {{ \App\Modules\Identity\Support\AuditPresenter::subjectLabel($event->subject_type) }}@if ($event->subject_id) <bdi dir="ltr" class="text-ink-secondary">#{{ $event->subject_id }}</bdi>@endif
    </span>
    <span class="text-xs text-ink-secondary">{{ $tenant ? __('identity::audit.tenant').': '.$tenant : __('identity::audit.platform') }}</span>
</span>
