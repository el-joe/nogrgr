{{--
    Admin › System tools › Cache tools (ADM-114, Figma Admin Panel 80:93684). Tabs
    (?tab=): Main cache (ADM-114a: application, config & routes, views, store texts)
    and Tenants cache (ADM-114b: store picker → storefront pages, translations, theme
    assets; "Clear all tenants"). Each action asks in the confirm dialog and shows when
    and by whom it last ran.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('system.cache.manage');
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $tabItems = [];
    foreach (\App\Modules\Platform\Http\Controllers\Admin\CacheToolsController::TABS as $key) {
        $tabItems[$key] = ['label' => __('platform::cache_tools.tabs.'.$key), 'href' => route('admin.system.cache.index', array_filter(['tab' => $key, 'store' => $key === 'tenants' ? $store?->id : null]))];
    }
@endphp
<x-layouts.panel surface="admin" :title="__('platform::cache_tools.title')" data-page="admin-system-tools">
    <x-ui::page-header
        :title="__('platform::cache_tools.title')"
        :subtitle="__('platform::cache_tools.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.system_tools') => null, __('platform::cache_tools.title') => null]"
    >
        <x-slot:tabs>
            <x-ui::tabs id="cache-tabs" :active="$tab" :label="__('platform::cache_tools.tabs_label')" :items="$tabItems" />
        </x-slot:tabs>
    </x-ui::page-header>

    <div class="grid max-w-3xl gap-6" data-cache-tools>
        @if ($tab === 'main')
            <x-ui::card :title="__('platform::cache_tools.main_title')" :subtitle="__('platform::cache_tools.main_hint')" id="main-cache">
                <ul class="grid divide-y divide-line">
                    @foreach ($mainActions as $action)
                        @include('platform::admin.system.cache._action-row', ['action' => $action, 'run' => $runs['main'][$action->value] ?? null, 'tenant' => null])
                    @endforeach
                </ul>
            </x-ui::card>
        @else
            <x-ui::card :title="__('platform::cache_tools.tenants_title')" :subtitle="__('platform::cache_tools.tenants_hint')" id="tenants-cache">
                <form method="GET" action="{{ route('admin.system.cache.index') }}" data-cache-store-form>
                    <input type="hidden" name="tab" value="tenants">
                    <x-ui::select
                        name="store"
                        :label="__('platform::cache_tools.store')"
                        :ajax-url="route('admin.system.cache.stores')"
                        :placeholder="__('platform::cache_tools.store_placeholder')"
                        :options="$store ? [$store->id => $store->name.' ('.$store->slug.')'] : []"
                        :selected="$store?->id"
                        data-cache-store
                    />
                </form>

                @if ($store)
                    <ul class="mt-4 grid divide-y divide-line">
                        @foreach ($storeActions as $action)
                            @include('platform::admin.system.cache._action-row', ['action' => $action, 'run' => $runs['store'][$action->value] ?? null, 'tenant' => $store])
                        @endforeach
                    </ul>
                @else
                    <div class="mt-4">
                        <x-ui::empty-state compact icon="store" :title="__('platform::cache_tools.pick_store_title')" :description="__('platform::cache_tools.pick_store_description')" />
                    </div>
                @endif

                <div class="mt-5 grid gap-2 border-t border-line pt-5">
                    @if ($canManage)
                        <div>
                            <x-ui::button
                                variant="danger"
                                icon="trash"
                                :data-confirm="__('platform::cache_tools.confirm.all_message')"
                                :data-confirm-title="__('platform::cache_tools.confirm.all_title', ['count' => $number($storeCount)])"
                                :data-confirm-label="__('platform::cache_tools.actions.all_stores.button')"
                                data-confirm-method="POST"
                                :data-confirm-url="route('admin.system.cache.clear', ['action' => \App\Modules\Platform\Enums\CacheAction::AllStores->value])"
                                :disabled="$storeCount === 0"
                            >{{ __('platform::cache_tools.clear_all', ['count' => $number($storeCount)]) }}</x-ui::button>
                        </div>
                    @endif
                    @include('platform::admin.system.cache._last-run', ['run' => $runs['all'], 'key' => \App\Modules\Platform\Enums\CacheAction::AllStores->value])
                </div>
            </x-ui::card>
        @endif
    </div>
</x-layouts.panel>
