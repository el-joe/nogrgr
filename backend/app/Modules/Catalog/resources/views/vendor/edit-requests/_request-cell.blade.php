<div class="grid">
    <span class="font-semibold text-ink" dir="ltr">ER-{{ $request->id }}</span>
    <span class="text-xs text-ink-secondary">{{ $request->created_at?->translatedFormat('j M') }}</span>
</div>
