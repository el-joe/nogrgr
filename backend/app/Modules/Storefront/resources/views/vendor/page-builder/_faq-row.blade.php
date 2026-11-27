{{-- One FAQ question / answer row of a page-builder FAQ section: $rowPrefix, $row, $rowId, $locales, $canManage. --}}
<li class="grid gap-3 rounded-control bg-subtle p-3" data-faq-row>
    @foreach ($locales as $code => $language)
        @php $languageName = $language['native'] ?? $language['name'] ?? strtoupper($code); @endphp
        <div class="grid gap-2" lang="{{ $code }}" dir="{{ $language['dir'] ?? 'ltr' }}">
            <x-ui::input :name="$rowPrefix.'[q]['.$code.']'" :id="'pb-faq-'.$rowId.'-q-'.$code" :label="__('content::page_builder.faq_question', ['language' => $languageName])" :value="$row['q'][$code] ?? null" maxlength="300" :disabled="! $canManage" />
            <x-ui::textarea :name="$rowPrefix.'[a]['.$code.']'" :id="'pb-faq-'.$rowId.'-a-'.$code" :label="__('content::page_builder.faq_answer', ['language' => $languageName])" :value="$row['a'][$code] ?? null" maxlength="2000" rows="3" :disabled="! $canManage" />
        </div>
    @endforeach
    @if ($canManage)
        <button type="button" class="inline-flex w-fit items-center gap-1 px-2 py-1 text-sm text-danger hover:bg-danger-bg" data-faq-remove><x-ui::icon name="trash" :size="14" />{{ __('content::page_builder.faq_remove') }}</button>
    @endif
</li>
