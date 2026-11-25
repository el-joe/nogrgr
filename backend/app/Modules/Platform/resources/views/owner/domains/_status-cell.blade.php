{{-- Request status: pending / connected / removed. --}}
<x-ui::badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui::badge>
