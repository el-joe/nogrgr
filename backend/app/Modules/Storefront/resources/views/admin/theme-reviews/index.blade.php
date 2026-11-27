{{--
    Admin › Content › Storefront › Theme review queue (ADM-089, Figma Admin Panel 78:80277):
    server-side list of submitted custom Twig theme versions across stores, filter by status
    (default: in review) and search; each row opens the review detail (ADM-089a).
--}}
<x-layouts.panel surface="admin" :title="__('content::custom_themes.review.title')" data-page="admin-theme-reviews">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('content::custom_themes.review.title')"
            :subtitle="__('content::custom_themes.review.subtitle')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.theme_review_queue') => null]"
        >
            <x-slot:actions>
                <x-ui::badge tone="warning">{{ __('content::custom_themes.review.pending_count', ['count' => $pending]) }}</x-ui::badge>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::datatable
            id="theme-reviews"
            :url="route('admin.storefront.theme-reviews.data')"
            :columns="[
                ['data' => 'store', 'title' => __('content::custom_themes.review.columns.store'), 'orderable' => false, 'searchable' => false],
                ['data' => 'theme', 'title' => __('content::custom_themes.review.columns.theme'), 'orderable' => false, 'searchable' => false],
                ['data' => 'submitted', 'title' => __('content::custom_themes.review.columns.submitted'), 'orderable' => false, 'searchable' => false],
                ['data' => 'validation', 'title' => __('content::custom_themes.review.columns.validation'), 'orderable' => false, 'searchable' => false],
                ['data' => 'status', 'title' => __('content::custom_themes.review.columns.status'), 'orderable' => false, 'searchable' => false],
                ['data' => 'reviewer', 'title' => __('content::custom_themes.review.columns.reviewer'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :empty-title="__('content::custom_themes.review.empty_title')"
            :empty-description="__('content::custom_themes.review.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="theme-reviews" :search-placeholder="__('content::custom_themes.review.search')">
                    <x-ui::select name="status" :label="__('content::custom_themes.review.status')" label-hidden :options="['' => __('content::custom_themes.review.all')] + $statuses" :selected="$status" :search="false" data-filter :data-filter-label="__('content::custom_themes.review.status')" class="w-56" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
