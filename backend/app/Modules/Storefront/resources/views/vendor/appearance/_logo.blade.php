{{-- Appearance › Logo (Figma 85:106903 "Current logo"): the store logo, replaced by upload. --}}
<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    <x-ui::card :title="__('content::store_appearance.logo.title')" :subtitle="__('content::store_appearance.logo.help')">
        @if ($logoUrl)
            <div class="grid h-40 place-items-center rounded-control bg-media p-4">
                <img src="{{ $logoUrl }}" alt="{{ __('content::store_appearance.logo.file') }}" class="max-h-full max-w-full object-contain" data-logo-preview>
            </div>
        @else
            <x-ui::empty-state compact icon="image" :title="__('content::store_appearance.logo.empty')" />
        @endif
    </x-ui::card>
    @if ($canManage)
        <x-ui::card :title="__('content::store_appearance.logo.upload')">
            <form method="POST" action="{{ vendor_route('store.appearance.logo') }}" enctype="multipart/form-data" data-ajax data-appearance-form="logo" novalidate class="grid gap-5">
                @csrf
                <x-ui::image-upload name="logo[]" :label="__('content::store_appearance.logo.file')" :max="1" accept="image/png,image/jpeg,image/webp,image/svg+xml" :helper="__('content::store_appearance.logo.help')" required />
                <div class="flex justify-end">
                    <x-ui::button type="submit" icon="check">{{ __('content::store_appearance.save') }}</x-ui::button>
                </div>
            </form>
        </x-ui::card>
    @endif
</div>
