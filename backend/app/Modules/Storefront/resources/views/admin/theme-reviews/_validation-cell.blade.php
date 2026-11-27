{{-- ADM-089 row: validator result (problems / warnings). --}}
@php
    $errorCount = count($version->errors());
    $warningCount = count($version->warnings());
@endphp
@if ($errorCount > 0)
    <x-ui::badge tone="danger">{{ __('content::custom_themes.review.errors_count', ['count' => $errorCount]) }}</x-ui::badge>
@elseif ($warningCount > 0)
    <x-ui::badge tone="warning">{{ __('content::custom_themes.review.warnings_count', ['count' => $warningCount]) }}</x-ui::badge>
@else
    <x-ui::badge tone="success">{{ __('content::custom_themes.review.clean') }}</x-ui::badge>
@endif
