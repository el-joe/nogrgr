<span class="grid">
    <span class="font-medium text-ink">{{ $attribute->name }}</span>
    @if ($attribute->status === \App\Modules\Catalog\Enums\RecordStatus::Inactive)
        <span class="text-xs text-ink-secondary">{{ $attribute->status->label() }}</span>
    @endif
</span>
