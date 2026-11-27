{{--
    Store hosts: the themed store 403 page (SF-ALL-004); other hosts: the framework minimal page.
    Framework / permission-package messages ("User does not have the right permissions.",
    "This action is unauthorized.") are replaced by a translated, friendly one; a message the
    app passed to abort(403, __('…')) is kept.
--}}
@include('errors._locale')
@php
    $raw = (string) ($exception?->getMessage() ?? '');
    $generic = $raw === ''
        || $exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException
        || in_array($raw, ['Forbidden', 'This action is unauthorized.', 'Unauthorized.'], true);
    $forbiddenMessage = $generic ? __('ui.http.forbidden') : __($raw);
@endphp
@if (\App\Modules\Affiliates\Support\AffiliateErrorPage::applies())
    @include('affiliates::errors.page', ['code' => '403', 'message' => $generic ? null : $forbiddenMessage])
@elseif ($panelSurface = \App\Shared\Http\PanelErrorPage::surface())
    @include(\App\Shared\Http\PanelErrorPage::VIEW, ['surface' => $panelSurface, 'code' => '403', 'message' => $generic ? null : $forbiddenMessage])
@elseif ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('403'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '403', 'message' => $forbiddenMessage])
@endif
