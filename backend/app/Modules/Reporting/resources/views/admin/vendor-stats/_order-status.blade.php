{{-- Order status pill (label from the Commerce OrderStatus enum). --}}
<x-ui::status :status="$status">{{ \App\Modules\Commerce\Enums\OrderStatus::tryFrom($status)?->label() ?? $status }}</x-ui::status>
