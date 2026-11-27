{{--
    "Send test" modal (list rows set the form action through email-templates.js).
    @param string|null $action
    @param string|null $email  recipient (default: the signed-in admin)
--}}
<x-ui::modal id="template-test-modal" :title="__('notifications::templates.send_test')" :subtitle="__('notifications::templates.send_test_help')" size="sm" close-on-success>
    <form id="template-test-form" method="POST" action="{{ $action ?? '' }}" data-ajax novalidate class="grid gap-4">
        @csrf
        <p class="text-sm text-ink-secondary">{{ __('notifications::templates.test_to', ['email' => (string) ($email ?? auth('admin')->user()?->email)]) }}</p>
        <x-ui::select name="locale" :label="__('notifications::templates.fields.locale')" :options="['en' => __('notifications::templates.locales.en'), 'ar' => __('notifications::templates.locales.ar')]" :selected="app()->getLocale()" :search="false" required />
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="template-test-form" icon="mail">{{ __('notifications::templates.send') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
