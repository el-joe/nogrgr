{{-- ADM-089 row: review status pill. --}}
<x-ui::badge :tone="$version->status->tone()">{{ $version->status->label() }}</x-ui::badge>
