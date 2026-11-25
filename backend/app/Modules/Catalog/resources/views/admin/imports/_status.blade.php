{{-- Import run status badge. @param \App\Modules\Catalog\Enums\ImportStatus $status --}}
<x-ui::badge :tone="$status->tone()">{{ $status->label() }}</x-ui::badge>
