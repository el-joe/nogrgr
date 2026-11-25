{{-- Status pill for any Billing status enum (tone() + label()). --}}
<x-ui::badge :tone="$status->tone()">{{ $status->label() }}</x-ui::badge>
