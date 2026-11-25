{{--
    Owner portal › Profile (OWN-006, Figma Affiliate & Owner 122:104562): personal
    information (e-mail read-only: it identifies the owner in every store), change /
    set password, and linked Google / Apple accounts. Unlink is disabled for the last
    sign-in method. Inputs: $owner, $providers, $hasPassword, $locales.
--}}
<x-layouts.panel surface="owner" :title="__('identity::owner_profile.title')" data-page="owner-profile">
    <x-ui::page-header :title="__('identity::owner_profile.title')" />

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="grid gap-6">
            <x-ui::card :title="__('identity::owner_profile.personal')">
                <form method="POST" action="{{ route('owner.profile.update') }}" class="grid max-w-xl gap-5" data-owner-profile-form novalidate>
                    @csrf
                    @method('PUT')
                    <x-ui::input name="name" :label="__('identity::owner_profile.fields.name')" :value="$owner->name" required maxlength="120" autocomplete="name" />
                    <x-ui::input name="email" type="email" :label="__('identity::owner_profile.fields.email')" :value="$owner->email" dir="ltr" disabled :helper="__('identity::owner_profile.email_hint')" />
                    <x-ui::phone name="phone" :label="__('identity::owner_profile.fields.phone')" :value="$owner->phone" required />
                    <x-ui::select name="locale" :label="__('identity::owner_profile.fields.language')" :options="$locales" :selected="$owner->locale ?? app()->getLocale()" :search="false" required />
                    <div class="flex justify-end">
                        <x-ui::button type="submit">{{ __('identity::owner_profile.save') }}</x-ui::button>
                    </div>
                </form>
            </x-ui::card>

            <x-ui::card :title="$hasPassword ? __('identity::owner_profile.change_password') : __('identity::owner_profile.set_password')" :subtitle="$hasPassword ? null : __('identity::owner_profile.set_password_hint')">
                <form method="POST" action="{{ route('owner.profile.password') }}" class="grid max-w-xl gap-5" data-owner-password-form novalidate>
                    @csrf
                    @method('PUT')
                    @if ($hasPassword)
                        <x-ui::input name="current_password" type="password" :label="__('identity::owner_profile.fields.current_password')" required autocomplete="current-password" />
                    @endif
                    <x-ui::input name="password" type="password" :label="__('identity::owner_profile.fields.new_password')" required autocomplete="new-password" :helper="__('identity::owner_profile.password_hint')" />
                    <x-ui::input name="password_confirmation" type="password" :label="__('identity::owner_profile.fields.confirm_password')" required autocomplete="new-password" />
                    <p class="text-xs text-ink-secondary">{{ __('identity::owner_profile.password_sync') }}</p>
                    <div class="flex justify-end">
                        <x-ui::button type="submit">{{ __('identity::owner_profile.update_password') }}</x-ui::button>
                    </div>
                </form>
            </x-ui::card>
        </div>

        <x-ui::card :title="__('identity::owner_profile.linked_title')" :subtitle="__('identity::owner_profile.linked_subtitle')">
            <ul class="grid divide-y divide-line">
                @foreach ($providers as $item)
                    @php $provider = $item['provider']; @endphp
                    <li class="flex items-center gap-3 py-3" data-provider="{{ $provider->value }}">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-subtle text-ink"><x-ui::icon :name="$provider->value" :size="18" /></span>
                        <div class="grid min-w-0 flex-1">
                            <span class="text-sm font-medium text-ink">{{ $provider->brand() }}</span>
                            <span class="truncate text-xs text-ink-secondary">
                                @if ($item['linked'])
                                    <bdi dir="ltr">{{ $owner->email }}</bdi> · {{ __('identity::owner_profile.linked') }}
                                @elseif (! $item['enabled'])
                                    {{ __('identity::owner_profile.unavailable') }}
                                @else
                                    {{ __('identity::owner_profile.not_linked_label') }}
                                @endif
                            </span>
                        </div>
                        @if ($item['linked'])
                            <form method="POST" action="{{ route('owner.profile.social.unlink', $provider->value) }}" data-confirm="{{ __('identity::owner_profile.unlink_confirm', ['provider' => $provider->brand()]) }}" data-confirm-title="{{ __('identity::owner_profile.unlink_title', ['provider' => $provider->brand()]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui::button type="submit" size="sm" variant="secondary" :disabled="! $item['canUnlink']">{{ __('identity::owner_profile.unlink') }}</x-ui::button>
                            </form>
                        @elseif ($item['enabled'])
                            <form method="POST" action="{{ route('owner.profile.social.link', $provider->value) }}">
                                @csrf
                                <x-ui::button type="submit" size="sm">{{ __('identity::owner_profile.link') }}</x-ui::button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @error('provider')
                <p class="mt-2 text-xs text-danger" role="alert">{{ $message }}</p>
            @enderror
            <p class="mt-4 flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-xs text-ink">
                <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />{{ __('identity::owner_profile.last_method_hint') }}
            </p>
        </x-ui::card>
    </div>
</x-layouts.panel>
