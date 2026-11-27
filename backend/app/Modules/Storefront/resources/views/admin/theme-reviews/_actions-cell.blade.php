{{-- ADM-089 row: open the review detail. --}}
<x-ui::button size="sm" variant="secondary" icon="eye" :href="route('admin.storefront.theme-reviews.show', $version->id)">{{ __('content::custom_themes.review.open') }}</x-ui::button>
