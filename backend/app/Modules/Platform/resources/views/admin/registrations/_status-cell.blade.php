{{-- Pending registrations › Status pill. --}}
<x-ui::badge :tone="$registration->status->tone()">{{ $registration->status->label() }}</x-ui::badge>
