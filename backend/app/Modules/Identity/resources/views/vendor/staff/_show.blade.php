{{-- Staff member details (VEN-076 view): contact, role, status, sign-ins and effective permissions. --}}
<div class="grid gap-5" data-modal-title="{{ $member->name }}">
    <div class="flex items-center gap-3">
        <x-ui::avatar :name="$member->name" :size="44" />
        <div class="grid min-w-0">
            <span class="flex items-center gap-2 truncate font-semibold text-ink">
                {{ $member->name }}
                @if ($locked)
                    <x-ui::badge tone="brand">{{ __('identity::staff.owner') }}</x-ui::badge>
                @endif
            </span>
            <span class="truncate text-xs text-ink-secondary"><bdi dir="ltr">{{ $member->email }}</bdi></span>
        </div>
    </div>
    <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
        <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('identity::staff.fields.role') }}</dt><dd class="text-ink">{{ \App\Shared\Support\Ui::roleLabel($role) ?? __('identity::staff.no_role') }}</dd></div>
        <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('identity::staff.columns.status') }}</dt><dd><x-ui::status :status="$member->status->value">{{ $member->status->label() }}</x-ui::status></dd></div>
        <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('identity::staff.fields.phone') }}</dt><dd class="text-ink"><bdi dir="ltr">{{ $member->phone ?? '—' }}</bdi></dd></div>
        <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('identity::staff.columns.last_login') }}</dt><dd class="text-ink">{{ $member->last_login_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm') ?? __('identity::staff.never_signed_in') }}</dd></div>
        <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('identity::staff.joined') }}</dt><dd class="text-ink">{{ $member->created_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY') }}</dd></div>
    </dl>
    <div class="grid gap-2">
        <h3 class="text-[13px] font-medium text-ink">{{ __('identity::staff.access') }}</h3>
        @if ($locked)
            <p class="text-[13px] text-ink-secondary">{{ __('identity::roles.all_permissions') }}</p>
        @elseif ($permissions === [])
            <p class="text-[13px] text-ink-secondary">{{ __('identity::staff.no_access') }}</p>
        @else
            <ul class="flex flex-wrap gap-1.5">
                @foreach (collect($permissions)->groupBy(fn (string $name): string => \Illuminate\Support\Str::beforeLast($name, '.')) as $resource => $names)
                    <li><x-ui::badge tone="neutral">{{ \App\Modules\Identity\Support\PermissionMatrix::resourceLabel($resource) }} · {{ collect($names)->map(fn (string $name): string => \App\Modules\Identity\Support\PermissionMatrix::abilityLabel(\Illuminate\Support\Str::afterLast($name, '.')))->join(', ') }}</x-ui::badge></li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
