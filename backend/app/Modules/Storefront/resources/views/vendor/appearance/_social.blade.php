{{-- Appearance › Social links (VEN-034f, Figma 85:107559): network icon, name, profile address, on / off, ⋯ (edit / delete); add in a modal. --}}
@php($allUsed = $socialLinks->count() >= count(\App\Modules\Storefront\Enums\SocialNetwork::cases()))
<x-ui::card :title="__('content::store_appearance.social.title')" :subtitle="__('content::store_appearance.social.help')">
    @if ($canManage)
        <x-slot:actions>
            <x-ui::button size="sm" icon="plus" data-remote-modal="appearance-modal" :data-url="vendor_route('store.appearance.social.create')" :disabled="$allUsed" :title="$allUsed ? __('content::store_appearance.social.all_used') : null">{{ __('content::store_appearance.social.add') }}</x-ui::button>
        </x-slot:actions>
    @endif
    @if ($socialLinks->isEmpty())
        <x-ui::empty-state compact icon="link" :title="__('content::store_appearance.social.empty')">
            @if ($canManage)
                <x-ui::button size="sm" icon="plus" data-remote-modal="appearance-modal" :data-url="vendor_route('store.appearance.social.create')">{{ __('content::store_appearance.social.add') }}</x-ui::button>
            @endif
        </x-ui::empty-state>
    @else
        <ul class="divide-y divide-line" role="list">
            @foreach ($socialLinks as $link)
                @php($network = $link->network->label())
                <li class="flex items-center gap-3 py-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="$link->network->icon()" :size="18" /></span>
                    <span class="grid min-w-0 flex-1 gap-0.5">
                        <span class="font-medium text-ink">{{ $network }}</span>
                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="truncate text-xs text-ink-secondary hover:underline" dir="ltr">{{ preg_replace('#^https?://(www\.)?#i', '', $link->url) }}</a>
                    </span>
                    @if ($canManage)
                        <x-ui::switch :name="'social_active_'.$link->id" :id="'social-active-'.$link->id" :checked="$link->is_active" :url="vendor_route('store.appearance.social.toggle', ['link' => $link->id])" field="is_active" :aria-label="__('content::store_appearance.social.show_named', ['network' => $network])" />
                        <x-ui::row-actions>
                            <x-ui::dropdown-item icon="edit" data-remote-modal="appearance-modal" :data-url="vendor_route('store.appearance.social.edit', ['link' => $link->id])">{{ __('content::store_appearance.social.edit') }}</x-ui::dropdown-item>
                            <x-ui::dropdown-divider />
                            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('content::store_appearance.social.delete_message')" :data-confirm-title="__('content::store_appearance.social.delete_title', ['network' => $network])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('store.appearance.social.destroy', ['link' => $link->id])">{{ __('content::store_appearance.social.delete') }}</x-ui::dropdown-item>
                        </x-ui::row-actions>
                    @else
                        <x-ui::status :status="$link->is_active ? 'active' : 'inactive'" />
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-ui::card>
