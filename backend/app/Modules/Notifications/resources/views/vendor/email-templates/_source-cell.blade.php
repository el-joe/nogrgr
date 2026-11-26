{{-- Custom (brand) | Default (neutral) | Built-in (neutral, standard text). --}}
@php($source = $row->source())
<x-ui::status :status="$source === 'custom' ? 'new' : 'neutral'">{{ __('notifications::vendor_templates.sources.'.$source) }}</x-ui::status>
