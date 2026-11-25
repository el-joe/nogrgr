{{--
    Admin › Catalog › Badges hub (ADM-017…017g, Figma Admin Panel 65:27887 / 65:29682):
    one tab per badge (New in, Best selling, Featured, Recommended, Trending now) with
    product counts; the badge's products sorted by drag and drop (saved at once), remove,
    the assign modal (search with multi-select, or a whole category) and badge settings.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('catalog.badges.manage');
    $count = $assignments->count();
    $tabItems = collect($tabs)->mapWithKeys(fn ($tab, $key) => [$key => [
        'label' => $tab['badge']->name,
        'count' => $tab['count'],
        'href' => route('admin.catalog.badges.show', $key),
    ]])->all();
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::badges.title')" data-page="admin-catalog">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('catalog::badges.title')"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('navigation.admin.labels_badges') => null, __('catalog::badges.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="layers" data-overlay-open="badge-assign-modal" data-assign-mode="category">{{ __('catalog::badges.assign_category') }}</x-ui::button>
                    <x-ui::button icon="plus" data-overlay-open="badge-assign-modal" data-assign-mode="search">{{ __('catalog::badges.assign_products') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="badge-tabs" :active="$type->value" :label="__('catalog::badges.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::card :title="__('catalog::badges.list_title', ['name' => $badge->name, 'count' => trans_choice('catalog::common.products_count', $count, ['count' => number_format($count)])])" :subtitle="__('catalog::badges.list_hint')">
                @if ($assignments->isEmpty())
                    <x-ui::empty-state icon="star" :title="__('catalog::badges.empty_title')" :description="__('catalog::badges.empty_description')">
                        @if ($canManage)
                            <x-ui::button icon="plus" data-overlay-open="badge-assign-modal" data-assign-mode="search">{{ __('catalog::badges.assign_products') }}</x-ui::button>
                        @endif
                    </x-ui::empty-state>
                @else
                    <ol @if ($canManage) data-sortable-list data-sort-url="{{ route('admin.catalog.badges.sort', $type) }}" data-sort-autosave @endif class="grid gap-2 aria-busy:opacity-60">
                        @foreach ($assignments as $assignment)
                            @php
                                $product = $assignment->product;
                            @endphp
                            @include('catalog::admin.partials.product-row', [
                                'product' => $product,
                                'subtitle' => trim(($product->categories->first()?->name ? $product->categories->first()->name.' · ' : '').$product->costBasis()->format()),
                                'remove' => $canManage ? route('admin.catalog.badges.remove', [$type, $product]) : null,
                            ])
                        @endforeach
                    </ol>
                @endif
            </x-ui::card>

            <x-ui::card :title="__('catalog::badges.settings')">
                <form method="POST" action="{{ route('admin.catalog.badges.update', $type) }}" data-ajax novalidate class="grid gap-4" data-badge-settings>
                    @csrf
                    @method('PUT')
                    @foreach ($locales as $code => $language)
                        <x-ui::input :name="'name['.$code.']'" :label="__('catalog::badges.fields.label_locale', ['locale' => strtoupper($code)])" :value="$badge->getTranslation('name', $code, false)" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="60" :dir="$language['dir']" :disabled="! $canManage" />
                    @endforeach
                    <x-ui::select name="auto_fill" :label="__('catalog::badges.fields.auto_fill')" :options="\App\Modules\Catalog\Enums\BadgeAutoFill::options()" :selected="$badge->auto_fill->value" :search="false" :disabled="! $canManage" data-auto-fill />
                    <div data-auto-fill-options @if (! $badge->auto_fill->isAutomatic()) hidden @endif class="grid gap-2">
                        <x-ui::input name="auto_fill_limit" type="number" min="1" max="200" :label="__('catalog::badges.fields.auto_fill_limit')" :value="$badge->auto_fill_limit" dir="ltr" :disabled="! $canManage" />
                        <p class="text-xs text-ink-secondary">{{ __('catalog::badges.auto_hint') }}</p>
                    </div>
                    <x-ui::switch name="is_active" :label="__('catalog::badges.fields.ribbon')" :checked="$badge->is_active" :disabled="! $canManage" />
                    @if ($canManage)
                        <x-ui::button type="submit" icon="check" class="justify-self-start">{{ __('catalog::badges.save_settings') }}</x-ui::button>
                    @endif
                </form>
            </x-ui::card>
        </div>
    </div>

    @if ($canManage)
        @include('catalog::admin.badges._assign-modal')
    @endif
</x-layouts.panel>
