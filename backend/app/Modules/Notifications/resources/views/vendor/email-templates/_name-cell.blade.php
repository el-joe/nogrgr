{{-- Template name + event key (Figma 91:114835 list row: mail icon, name, "overridden" / "default"). --}}
<div class="flex min-w-0 items-center gap-3">
    <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="mail" :size="18" /></span>
    <span class="grid min-w-0 gap-0.5">
        <span class="truncate font-semibold text-ink">{{ $row->event->label() }}</span>
        <span class="truncate text-xs text-ink-secondary">{{ __('notifications::vendor_templates.source_help.'.$row->source()) }} · <span dir="ltr">{{ $row->event->value }}</span></span>
    </span>
</div>
