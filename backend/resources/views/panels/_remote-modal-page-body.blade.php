{{-- Body of panels.remote-modal-page (shared by the vendor and admin / affiliate shells). --}}
    <div class="mx-auto grid w-full max-w-3xl gap-6" data-remote-modal-page @if ($page->formId) data-success-url="{{ $page->backUrl }}" @endif>
        <x-ui::page-header :title="$page->title" :breadcrumbs="$breadcrumbs">
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" :href="$page->backUrl">{{ __('ui.back') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::card>
            @include($fragment, $fragmentData)

            @if ($page->formId)
                <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-line pt-5">
                    <x-ui::button variant="secondary" :href="$page->backUrl">{{ __('ui.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" :form="$page->formId" icon="check">{{ $page->submitLabel ?? __('ui.save') }}</x-ui::button>
                </div>
            @endif
        </x-ui::card>
    </div>
