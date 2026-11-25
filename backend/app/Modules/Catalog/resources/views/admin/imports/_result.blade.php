{{-- Log line result badge. @param \App\Modules\Catalog\Enums\ImportLogResult $result --}}
<x-ui::badge :tone="$result->tone()">{{ $result->label() }}</x-ui::badge>
