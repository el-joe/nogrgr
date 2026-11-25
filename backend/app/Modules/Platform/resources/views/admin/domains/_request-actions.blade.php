{{--
    Domain request actions shared by the list menu ($asMenu) and the detail header:
    run a DNS check (queued), reopen, remove, connect. Only valid transitions; the
    caller checks vendors.domains.manage.
--}}
@use('App\Modules\Platform\Enums\DomainRequestStatus')
@php
    $actions = [];
    if ($request->status !== DomainRequestStatus::Removed) {
        $actions[] = ['key' => 'check', 'icon' => 'refresh', 'attributes' => ['data-review-request' => '', 'data-method' => 'POST', 'data-url' => route('admin.vendors.domains.check', $request->id)]];
    }
    foreach ([
        'reopen' => [DomainRequestStatus::Pending, 'refresh', 'primary'],
        'remove' => [DomainRequestStatus::Removed, 'x-circle', 'danger'],
        'connect' => [DomainRequestStatus::Connected, 'link', 'primary'],
    ] as $key => [$target, $icon, $tone]) {
        if ($request->status->canTransitionTo($target)) {
            $actions[] = ['key' => $key, 'icon' => $icon, 'tone' => $tone, 'attributes' => [
                'data-confirm' => __('platform::domains.actions.'.$key.'_message'),
                'data-confirm-title' => __('platform::domains.actions.'.$key.'_title', ['host' => $request->host]),
                'data-confirm-label' => __('platform::domains.actions.'.$key),
                'data-confirm-tone' => $tone,
                'data-confirm-method' => 'POST',
                'data-confirm-url' => route('admin.vendors.domains.'.$key, $request->id),
            ]];
        }
    }
@endphp
@foreach ($actions as $action)
    @php
        $attributes = new \Illuminate\View\ComponentAttributeBag($action['attributes']);
    @endphp
    @if ($asMenu)
        @if (($action['tone'] ?? null) === 'danger')
            <x-ui::dropdown-divider />
        @endif
        <x-ui::dropdown-item :icon="$action['icon']" :tone="($action['tone'] ?? null) === 'danger' ? 'danger' : null" {{ $attributes }}>{{ __('platform::domains.actions.'.$action['key']) }}</x-ui::dropdown-item>
    @else
        <x-ui::button :variant="$action['key'] === 'connect' ? 'primary' : (($action['tone'] ?? null) === 'danger' ? 'danger' : 'secondary')" :icon="$action['icon']" {{ $attributes }}>{{ __('platform::domains.actions.'.$action['key']) }}</x-ui::button>
    @endif
@endforeach
