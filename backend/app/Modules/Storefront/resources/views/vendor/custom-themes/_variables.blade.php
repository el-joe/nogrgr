{{-- Variable reference table of the developer guide (rows from ThemeVariableReference). --}}
<div class="relative overflow-x-auto rounded-card border border-line">
    <table class="w-full min-w-[560px] text-[13px]" dir="ltr">
        <thead>
            <tr class="bg-subtle text-start text-ink-secondary">
                <th scope="col" class="px-3 py-2 text-start font-normal">{{ __('content::custom_themes.docs.variable') }}</th>
                <th scope="col" class="px-3 py-2 text-start font-normal">{{ __('content::custom_themes.docs.type') }}</th>
                <th scope="col" class="px-3 py-2 text-start font-normal">{{ __('content::custom_themes.docs.example') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-t border-line">
                    <td class="px-3 py-1.5 font-mono text-ink">{{ $row['path'] }}</td>
                    <td class="px-3 py-1.5 text-ink-secondary">{{ $row['type'] }}</td>
                    <td class="max-w-[320px] truncate px-3 py-1.5 font-mono text-ink-secondary">{{ $row['example'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
