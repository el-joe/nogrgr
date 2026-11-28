{{--
    Elora v1 section heading. style: trending (1:15646, 48px gradient title, see all at the
    end) | split (1:15727, see all at the start) | center (1:15803, no link on desktop).
    Phones (m 1:21269): icon + title at the start, "see all" at the end.
--}}
@props(['title', 'icon', 'style' => 'split', 'more' => null, 'subtitle' => null])
<div {{ $attributes->class(['v1-heading', 'v1-heading-'.$style]) }} data-reveal="fade-up">
    <h2 class="v1-heading-title">
        <span class="v1-ico v1-ico-{{ $icon }}" aria-hidden="true"></span>
        <span class="v1-grad-text">{{ $title }}</span>
    </h2>
    @if ($more)
        <a href="{{ $more }}" class="v1-see-all">
            <span class="v1-grad-text">{{ __st('elora.home.see_all_link') }}</span>
            <span class="v1-ico v1-ico-see-all" aria-hidden="true"></span>
        </a>
    @endif
    @if ($subtitle)
        <p class="v1-heading-sub">{{ $subtitle }}</p>
    @endif
</div>
