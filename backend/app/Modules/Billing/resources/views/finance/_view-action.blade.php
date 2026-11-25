{{-- Eye button that opens a finance detail modal: $modal id, $url, $label. --}}
<div class="flex justify-end">
    <x-ui::icon-button icon="eye" size="sm" :label="$label" :data-remote-modal="$modal" :data-url="$url" />
</div>
