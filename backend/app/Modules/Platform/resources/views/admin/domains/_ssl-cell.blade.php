{{-- SSL pill: valid / invalid / unknown ("—"). --}}
@use('App\Modules\Platform\Enums\SslStatus')
@if ($request->ssl_status === SslStatus::Unknown)
    <x-ui::badge tone="neutral"><span aria-hidden="true">—</span><span class="sr-only">{{ $request->ssl_status->label() }}</span></x-ui::badge>
@else
    <x-ui::badge :tone="$request->ssl_status->tone()">{{ $request->ssl_status->label() }}</x-ui::badge>
@endif
