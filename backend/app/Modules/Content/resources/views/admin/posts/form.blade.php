{{--
    Admin › Website › Blog posts › Create / Edit (ADM-081a, Figma Admin Panel 76:72347):
    per-locale title, excerpt, rich body and SEO fields; slug, category, author, status
    + publish date (future = scheduled) and the cover (webp / avif generated on upload).
--}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $isNew = ! $post->exists;
    $title = $isNew ? __('content::admin.posts.create_title') : __('content::admin.posts.edit_title', ['name' => $post->title]);
    $cover = $post->coverSources();
    $coverUrl = $cover['webp'] ?? $cover['src'];
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-content">
    <form method="POST" action="{{ $isNew ? route('admin.content.posts.store') : route('admin.content.posts.update', $post) }}" data-ajax data-unsaved-form data-content-form class="grid gap-6" enctype="multipart/form-data" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-ui::page-header :title="$title"
            :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.posts.title') => route('admin.content.posts.index'), ($isNew ? __('content::admin.posts.new') : $post->title) => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.content.posts.index')">{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('content::admin.common.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
            <x-ui::card :title="__('content::admin.common.translations')">
                <x-ui::locale-tabs id="post-i18n">
                    @foreach ($locales as $code => $language)
                        <x-ui::locale-pane parent="post-i18n" :locale="$code">
                            <div class="grid gap-5">
                                <x-ui::input :name="'title['.$code.']'" :label="__('content::admin.posts.fields.title')" :value="$post->getTranslation('title', $code, false)" :required="$code === 'en'" maxlength="200" :dir="$language['dir']" :data-slug-source="$code === 'en' ? 'true' : null" />
                                <x-ui::textarea :name="'excerpt['.$code.']'" :label="__('content::admin.posts.fields.excerpt')" :value="$post->getTranslation('excerpt', $code, false)" :rows="2" :maxlength="400" :dir="$language['dir']" />
                                @include('content::admin.partials.rich-editor', ['name' => 'body['.$code.']', 'errorKey' => 'body.'.$code, 'label' => __('content::admin.posts.fields.body'), 'value' => $post->getTranslation('body', $code, false), 'dir' => $language['dir'], 'required' => $code === 'en', 'minHeight' => 'min-h-80'])
                                @include('content::admin.partials.seo-fields', ['model' => $post, 'code' => $code, 'dir' => $language['dir']])
                            </div>
                        </x-ui::locale-pane>
                    @endforeach
                </x-ui::locale-tabs>
            </x-ui::card>

            <div class="grid gap-6">
                <x-ui::card :title="__('content::admin.common.settings')">
                    <div class="grid gap-5">
                        <x-ui::select name="status" :label="__('content::admin.posts.fields.status')" :options="$statuses" :selected="$post->status?->value" :search="false" required />
                        <x-ui::input name="published_at" type="datetime-local" :label="__('content::admin.posts.fields.published_at')" :value="$post->published_at?->format('Y-m-d\TH:i')" :helper="__('content::admin.posts.fields.published_at_help')" dir="ltr" />
                        <x-ui::select name="blog_category_id" :label="__('content::admin.posts.fields.category')" :options="$categories" :selected="$post->blog_category_id" :placeholder="__('content::admin.posts.fields.no_category')" allow-clear />
                        <x-ui::input name="author_name" :label="__('content::admin.posts.fields.author')" :value="$post->author_name" maxlength="120" />
                        <x-ui::input name="slug" :label="__('content::admin.common.slug')" :value="$post->slug" :helper="__('content::admin.common.slug_help')" dir="ltr" maxlength="160" data-slug-target />
                    </div>
                </x-ui::card>
                <x-ui::card :title="__('content::admin.posts.fields.cover')">
                    <x-ui::image-upload name="cover" :max="1" accept="image/jpeg,image/png,image/webp" :images="$coverUrl ? [['id' => 'current', 'url' => $coverUrl]] : []" :helper="__('content::admin.posts.fields.cover_help')" :label="__('content::admin.posts.fields.cover')" />
                </x-ui::card>
            </div>
        </div>
    </form>
</x-layouts.panel>
