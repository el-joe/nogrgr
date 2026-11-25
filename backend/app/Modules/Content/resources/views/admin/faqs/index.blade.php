{{-- Admin › Website › FAQs (ADM-083, Figma Admin Panel 76:74912): drag-sort list (saved on drop), edit, delete. --}}
@php $canManage = (bool) auth('admin')->user()?->can('content.faqs.manage'); @endphp
<x-layouts.panel surface="admin" :title="__('content::admin.faqs.title')" data-page="admin-content">
    <x-ui::page-header :title="__('content::admin.faqs.title')" :subtitle="__('content::admin.faqs.subtitle')"
        :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.faqs.title') => null]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.content.faqs.create')">{{ __('content::admin.faqs.new') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    @if ($faqs->isEmpty())
        <x-ui::card>
            <x-ui::empty-state :title="__('content::admin.faqs.empty_title')" :description="__('content::admin.faqs.empty_description')" icon="message">
                @if ($canManage)
                    <x-ui::button icon="plus" :href="route('admin.content.faqs.create')">{{ __('content::admin.faqs.new') }}</x-ui::button>
                @endif
            </x-ui::empty-state>
        </x-ui::card>
    @else
        <x-ui::card :padding="false">
            <ol class="divide-y divide-line" @if ($canManage) data-faq-sort="{{ route('admin.content.faqs.sort') }}" @endif>
                @foreach ($faqs as $faq)
                    <li class="flex items-center gap-3 bg-surface px-4 py-3" data-id="{{ $faq->id }}">
                        @if ($canManage)
                            <span class="cursor-grab text-ink-muted" data-faq-handle title="{{ __('content::admin.faqs.drag') }}" aria-hidden="true"><x-ui::icon name="grid" :size="16" /></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ $faq->question }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                                <span>{{ $faq->group->label() }}</span>
                                @unless ($faq->is_active)
                                    <x-ui::badge>{{ __('content::admin.faqs.inactive') }}</x-ui::badge>
                                @endunless
                            </p>
                        </div>
                        @if ($canManage)
                            <x-ui::icon-button icon="edit" size="sm" :href="route('admin.content.faqs.edit', $faq)" :label="__('content::admin.common.edit')" />
                            <x-ui::row-actions>
                                <x-ui::dropdown-item icon="edit" :href="route('admin.content.faqs.edit', $faq)">{{ __('content::admin.common.edit') }}</x-ui::dropdown-item>
                                <x-ui::dropdown-divider />
                                @include('content::admin.partials.delete-item', ['url' => route('admin.content.faqs.destroy', $faq), 'name' => \Illuminate\Support\Str::limit($faq->question, 60)])
                            </x-ui::row-actions>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-ui::card>
    @endif
</x-layouts.panel>
