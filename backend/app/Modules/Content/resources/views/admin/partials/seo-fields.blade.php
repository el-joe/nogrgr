{{-- SEO fields of one locale pane (meta title / description). Inputs: $model, $code, $dir. --}}
<div class="grid gap-5 rounded-control border border-dashed border-line p-4">
    <p class="text-[13px] font-semibold text-ink">{{ __('content::admin.common.seo') }}</p>
    <x-ui::input :name="'meta_title['.$code.']'" :label="__('content::admin.common.meta_title')" :value="$model->getTranslation('meta_title', $code, false)" :helper="__('content::admin.common.meta_title_help')" maxlength="70" :dir="$dir" />
    <x-ui::textarea :name="'meta_description['.$code.']'" :label="__('content::admin.common.meta_description')" :value="$model->getTranslation('meta_description', $code, false)" :helper="__('content::admin.common.meta_description_help')" :rows="2" :maxlength="170" :dir="$dir" />
</div>
