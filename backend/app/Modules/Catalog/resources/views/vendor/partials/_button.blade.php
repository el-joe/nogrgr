{{-- Header CTA (vnd-cta) rendered to a string for the welcome header actions. --}}
<a href="{{ $href }}" class="vnd-cta {{ ($variant ?? 'primary') === 'primary' ? 'vnd-cta-primary' : 'vnd-cta-outline' }} flex-1 sm:w-[211px] sm:flex-none" {!! $attributes ?? '' !!}><x-ui::icon :name="$icon" :size="22" />{{ $label }}</a>
