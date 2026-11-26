{{--
    Staff status (Figma 90:115176): a badge on every row — Active / Invited / Inactive.
    Activate / deactivate lives in the row actions menu (QA-P8-011, decision #86).
--}}
@if ($member->status === \App\Modules\Identity\Enums\AccountStatus::Pending)
    <x-ui::status status="pending">{{ __('identity::staff.invited_status') }}</x-ui::status>
@else
    <x-ui::status :status="$member->status->value">{{ $member->status->label() }}</x-ui::status>
@endif
