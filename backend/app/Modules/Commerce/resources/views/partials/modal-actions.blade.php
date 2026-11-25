{{-- Footer of a remote-modal form fragment: Close (modal) + submit. $submit label, $tone primary|danger, $icon. --}}
<div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
    {{ $slot ?? '' }}
    <x-ui::button variant="secondary" data-overlay-close>{{ $cancel ?? __('commerce::orders_admin.close') }}</x-ui::button>
    <x-ui::button type="submit" :variant="$tone ?? 'primary'" :icon="$icon ?? 'check'">{{ $submit }}</x-ui::button>
</div>
