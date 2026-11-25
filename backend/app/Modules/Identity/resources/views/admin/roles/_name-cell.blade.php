<span class="flex items-center gap-2">
    <span class="font-medium text-ink">{{ \App\Shared\Support\Ui::roleLabel($role->name) }}</span>
    @if ($locked)
        <x-ui::badge tone="neutral"><x-ui::icon name="key" :size="12" /> {{ __('identity::roles.locked_badge') }}</x-ui::badge>
    @endif
</span>
