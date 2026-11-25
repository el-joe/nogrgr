<a href="{{ $url }}" class="block font-semibold text-ink hover:text-ink-brand" dir="ltr">{{ $return->reference }}</a>
<span class="text-xs text-ink-secondary">{{ $return->created_at?->translatedFormat('j M Y') }} · {{ $return->type->label() }}</span>
