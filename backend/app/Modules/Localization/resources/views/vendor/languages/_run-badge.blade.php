{{-- Status badge of a bought language's paid AI translation run (refreshed live). --}}
<x-ui::badge :tone="$run->status->tone()" dot>{{ $run->status->label() }}</x-ui::badge>
