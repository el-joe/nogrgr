<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.requests.edits.show', $request->id)" :label="__('catalog::requests.actions.view', ['reference' => $request->reference()])" />
</div>
