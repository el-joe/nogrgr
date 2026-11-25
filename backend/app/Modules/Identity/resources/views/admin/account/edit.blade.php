{{--
    Admin › My profile (ADM-005, Figma Admin Panel 80:97736): profile card (avatar,
    name, email, language, timezone) and password card (current + new + confirm).
    Each card is its own form so a password change never resubmits the profile.
--}}
@php
    /** @var \App\Modules\Identity\Models\Admin $admin */
    $images = $admin->avatar_url ? [['id' => 'current', 'url' => $admin->avatar_url, 'alt' => $admin->name]] : [];
@endphp
<x-layouts.panel surface="admin" :title="__('identity::profile.title')" data-page="admin-account">
    <x-ui::page-header
        :title="__('identity::profile.title')"
        :subtitle="__('identity::profile.subtitle')"
        :breadcrumbs="[__('identity::profile.breadcrumb') => null, __('identity::profile.title') => null]"
    >
        <x-slot:actions>
            <x-ui::button type="submit" form="profile-form" icon="check">{{ __('identity::profile.save') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::page-header>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <x-ui::card :title="__('identity::profile.profile')" id="profile-card">
            <form id="profile-form" method="POST" action="{{ route('admin.account.update') }}" enctype="multipart/form-data" data-ajax novalidate class="grid gap-5">
                @csrf
                @method('PUT')
                <x-ui::image-upload name="avatar" :label="__('identity::profile.fields.avatar')" :images="$images" :max="1" accept="image/jpeg,image/png,image/webp" :helper="__('identity::profile.fields.avatar_help')" />
                <x-ui::input name="name" :label="__('identity::profile.fields.name')" :value="$admin->name" required maxlength="120" autocomplete="name" />
                <x-ui::input name="email" type="email" :label="__('identity::profile.fields.email')" :value="$admin->email" required maxlength="191" dir="ltr" autocomplete="email" />
                <x-ui::select name="locale" :label="__('identity::profile.fields.language')" :options="$languages" :selected="$admin->locale ?? app()->getLocale()" :search="false" />
                <x-ui::select name="timezone" :label="__('identity::profile.fields.timezone')" :options="$timezones" :selected="$admin->timezone ?? config('app.timezone')" :helper="__('identity::profile.fields.timezone_help')" />
                <div class="flex justify-end">
                    <x-ui::button type="submit" icon="check">{{ __('identity::profile.save') }}</x-ui::button>
                </div>
            </form>
        </x-ui::card>

        <x-ui::card :title="__('identity::profile.password')" id="password-card">
            <form method="POST" action="{{ route('admin.account.password') }}" data-ajax data-reset-on-success novalidate class="grid gap-5">
                @csrf
                @method('PUT')
                <x-ui::input name="current_password" type="password" :label="__('identity::profile.fields.current_password')" required autocomplete="current-password" dir="ltr" />
                <x-ui::input name="password" type="password" :label="__('identity::profile.fields.new_password')" :helper="__('identity::profile.fields.password_help')" required autocomplete="new-password" dir="ltr" minlength="12" />
                <x-ui::input name="password_confirmation" type="password" :label="__('identity::profile.fields.new_password_confirmation')" required autocomplete="new-password" dir="ltr" minlength="12" />
                <div class="flex justify-end">
                    <x-ui::button type="submit" variant="secondary" icon="key">{{ __('identity::profile.change_password') }}</x-ui::button>
                </div>
            </form>
        </x-ui::card>
    </div>
</x-layouts.panel>
