{{-- Account status pill (active / suspended / pending). --}}
<x-ui::status :status="$customer->status->value">{{ $customer->status->label() }}</x-ui::status>
