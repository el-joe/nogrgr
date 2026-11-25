{{-- Round state badge (Figma 78px circle): $tone = primary|success|danger|spinner, $icon. --}}
@if (($tone ?? '') === 'spinner')
    <span class="reg-spinner size-14 rounded-full border-4 border-(--reg-ring) border-t-primary" aria-hidden="true"></span>
@else
    <span @class([
        'reg-pop grid size-[78px] place-items-center rounded-full',
        'bg-(--reg-tint) text-primary' => ($tone ?? 'primary') === 'primary',
        'bg-success-bg text-success' => ($tone ?? '') === 'success',
        'bg-danger-bg text-danger' => ($tone ?? '') === 'danger',
    ]) aria-hidden="true"><x-ui::icon :name="$icon" :size="32" /></span>
@endif
