{{-- Internal notes (admins only), saved through the notes endpoint. --}}
<x-ui::card :title="__('catalog::requests.notes.title')" id="request-notes">
    <form method="POST" action="{{ route($request->kind()->routePrefix().'.notes', $request->id) }}" data-ajax novalidate class="grid gap-3">
        @csrf
        @method('PUT')
        <x-ui::textarea name="admin_notes" :label="__('catalog::requests.notes.label')" :value="$request->admin_notes" :placeholder="__('catalog::requests.notes.placeholder')" maxlength="5000" rows="4" :disabled="! $canManage" />
        @if ($canManage)
            <div class="flex justify-end">
                <x-ui::button type="submit" size="sm" variant="secondary" icon="check">{{ __('catalog::requests.notes.save') }}</x-ui::button>
            </div>
        @endif
    </form>
</x-ui::card>
