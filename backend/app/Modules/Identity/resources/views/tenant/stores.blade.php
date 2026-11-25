{{-- Store picker after a central-host vendor login that matched several stores. --}}
<x-layouts.auth surface="vendor" :title="__('identity::auth.choose_store')">
    <h1 class="text-2xl font-semibold">{{ __('identity::auth.choose_store') }}</h1>
    <ul class="mt-6 grid gap-3">
        @foreach ($stores as $store)
            <li>
                <form method="POST" action="{{ route('central.tenant.stores.select') }}">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $store->id }}">
                    <button type="submit" class="w-full rounded border px-4 py-3 text-start" data-store="{{ $store->slug }}">
                        {{ $store->name }}
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
    @error('tenant_id')
        <p class="mt-3 text-sm text-danger">{{ $message }}</p>
    @enderror
</x-layouts.auth>
