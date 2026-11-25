{{-- Row "Delete" dropdown item with the shared confirm dialog. Inputs: $url, $name, $message. --}}
<x-ui::dropdown-item
    icon="trash"
    tone="danger"
    :data-confirm="$message ?? __('content::admin.common.confirm_delete_message')"
    :data-confirm-title="__('content::admin.common.confirm_delete_title', ['name' => $name])"
    data-confirm-method="DELETE"
    :data-confirm-url="$url"
>{{ __('ui.delete') }}</x-ui::dropdown-item>
