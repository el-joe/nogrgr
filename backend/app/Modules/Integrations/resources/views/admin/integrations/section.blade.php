{{--
    Admin › Integrations › one settings tab (ADM-100d Mail, 100e Google login, 100f Apple
    login, 100h Reverb/Pusher, 100j Cloudflare/DNS, AI translation — Figma Admin Panel
    53:18783 / 79:87472). One form per tab; secrets masked; test result inline + toast.
--}}
@php
    use App\Modules\Ai\Enums\TranslationProvider;
    use App\Modules\Integrations\Enums\IntegrationSection;

    /** @var IntegrationSection $section */
    $canManage = (bool) auth('admin')->user()?->can('integrations.manage');
    $base = 'integrations::admin.sections.'.$section->value;
    $fields = collect($section->fields())->keyBy('key');
    $testUrl = route('admin.integrations.test', $section);
@endphp
<x-layouts.panel surface="admin" :title="$section->label().' · '.__('integrations::admin.title')" data-page="admin-integrations">
    @include('integrations::admin.integrations._header', ['active' => $section->value])

    <form id="integration-form" method="POST" action="{{ route('admin.integrations.update', $section) }}" enctype="multipart/form-data" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')

        @if ($section === IntegrationSection::Ai)
            <x-ui::card>
                <div class="mb-5 flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink"><x-ui::icon :name="$section->icon()" :size="20" /></span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">{{ __($base.'.title') }}</h2>
                        <p class="text-[13px] text-ink-secondary">{{ __($base.'.description') }}</p>
                    </div>
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    @foreach (['provider', 'custom_provider', 'paid_provider'] as $key)
                        @include('integrations::admin.integrations._field', ['field' => $fields[$key], 'state' => $form[$key], 'editable' => $canManage])
                    @endforeach
                </div>
            </x-ui::card>

            <div class="grid items-start gap-6 lg:grid-cols-2">
                @foreach (TranslationProvider::cases() as $provider)
                    <x-ui::card>
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink"><x-ui::icon :name="$provider === TranslationProvider::OpenAi ? 'openai' : 'server'" :size="20" /></span>
                                <div class="min-w-0">
                                    <h3 class="text-base font-semibold text-ink">{{ $provider->label() }}</h3>
                                    <p class="text-[13px] text-ink-secondary">{{ __($base.'.providers.'.$provider->value) }}</p>
                                </div>
                            </div>
                            @include('integrations::admin.integrations._status', ['key' => $provider->value, 'status' => $statuses[$provider->value]['status']])
                        </div>
                        <div class="grid gap-5">
                            @foreach (['base_url', 'api_key', 'model'] as $suffix)
                                @include('integrations::admin.integrations._field', ['field' => $fields[$provider->value.'_'.$suffix], 'state' => $form[$provider->value.'_'.$suffix], 'editable' => $canManage])
                            @endforeach
                        </div>
                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                            @include('integrations::admin.integrations._last-test', ['key' => $provider->value, 'last' => $statuses[$provider->value]['last']])
                            @if ($canManage)
                                <x-ui::button variant="secondary" size="sm" icon="zap" data-integration-test="{{ $testUrl }}" data-test-target="{{ $provider->value }}">{{ __('integrations::admin.test.button') }}</x-ui::button>
                            @endif
                        </div>
                    </x-ui::card>
                @endforeach
            </div>
            <p class="text-xs text-ink-secondary">{{ __($base.'.save_first') }}</p>
        @else
            <x-ui::card class="max-w-3xl">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink"><x-ui::icon :name="$section->icon()" :size="20" /></span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-ink">{{ __($base.'.title') }}</h2>
                            <p class="text-[13px] text-ink-secondary">{{ __($base.'.description') }}</p>
                        </div>
                    </div>
                    @include('integrations::admin.integrations._status', ['key' => $section->value, 'status' => $statuses['']['status']])
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ($section->fields() as $field)
                        @include('integrations::admin.integrations._field', ['field' => $field, 'state' => $form[$field->key], 'editable' => $canManage])
                    @endforeach

                    @if ($redirectUri)
                        <div class="grid gap-1.5 md:col-span-2">
                            <span class="text-sm font-medium text-ink">{{ __('integrations::admin.redirect_uri') }}</span>
                            <div class="flex items-center gap-2">
                                <code class="ui-control min-w-0 flex-1 truncate text-[13px]" dir="ltr">{{ $redirectUri }}</code>
                                <x-ui::icon-button icon="copy" :label="__('integrations::admin.copy_redirect')" data-copy-text="{{ $redirectUri }}" />
                            </div>
                            <span class="text-xs text-ink-secondary">{{ __($base.'.redirect_help') }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                    @if ($section === IntegrationSection::Mail)
                        <span class="text-xs text-ink-secondary">{{ __($base.'.test_help') }}</span>
                    @else
                        @include('integrations::admin.integrations._last-test', ['key' => $section->value, 'last' => $statuses['']['last']])
                    @endif
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($canManage)
                            @if ($section === IntegrationSection::Mail)
                                <x-ui::button variant="ghost" icon="mail" data-overlay-open="mail-test-modal">{{ __('integrations::admin.mail.send_test') }}</x-ui::button>
                            @else
                                <x-ui::button variant="ghost" icon="zap" data-integration-test="{{ $testUrl }}">{{ __($base.'.test') }}</x-ui::button>
                            @endif
                            <x-ui::button type="submit" icon="check">{{ __('integrations::admin.save') }}</x-ui::button>
                        @endif
                    </div>
                </div>
            </x-ui::card>
        @endif

        @if ($section === IntegrationSection::Ai && $canManage)
            <div class="flex justify-end">
                <x-ui::button type="submit" icon="check">{{ __('integrations::admin.save') }}</x-ui::button>
            </div>
        @endif
    </form>

    @if ($section === IntegrationSection::Mail && $canManage)
        <x-ui::modal id="mail-test-modal" :title="__('integrations::admin.mail.send_test')" :subtitle="__('integrations::admin.mail.test_modal_help')" size="sm" close-on-success>
            <form id="mail-test-form" method="POST" action="{{ $testUrl }}" data-ajax novalidate class="grid gap-4">
                @csrf
                <x-ui::input name="email" type="email" :label="__('integrations::admin.mail.test_recipient')" :value="auth('admin')->user()?->email" required dir="ltr" icon="mail" />
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="mail-test-form" icon="mail">{{ __('integrations::admin.mail.send') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
