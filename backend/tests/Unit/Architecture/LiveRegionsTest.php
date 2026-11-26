<?php

/*
| QA-P8-005: after a row switch the row's badge stayed stale, and quota lines / tab counts /
| staff seat lines stayed stale after create, delete or invite, because the shared switch
| handler never reloaded the table or told the page. The switch now redraws its rows in
| place and every change (switch, remote-modal save, confirmed action) refreshes the page's
| [data-live] regions.
*/

$js = fn (string $path): string => (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/panels/'.$path);

it('redraws the row and notifies the page after a successful switch', function () use ($js): void {
    expect($js('ui/fields.js'))
        ->toContain("import { notifyChanged } from './live';")
        ->toMatch("/await http\\.patch\\(input\\.dataset\\.switchUrl[\\s\\S]*?new CustomEvent\\('switch:changed'[\\s\\S]*?refreshRow\\(input\\);\\s*notifyChanged\\(/")
        ->toContain("new CustomEvent('datatable:reload', { detail: { keepPage: true, quiet: true } })");
});

it('refreshes live regions after modal saves and confirmed actions', function () use ($js): void {
    expect($js('remote-modal.js'))->toContain("notifyChanged({ source: 'modal' })")
        ->and($js('ui/live.js'))->toContain("document.addEventListener('panel:changed', schedule)", "document.addEventListener('confirm:done', schedule)", '[data-live="${CSS.escape(region.dataset.live)}"]')
        ->and($js('ui/index.js'))->toContain('initLiveRegions();')
        ->and($js('datatable.js'))->toContain('card.dataset.quietReload === undefined', "new CustomEvent('datatable:drawn')");
});

it('marks tab count pills as live regions', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/components/ui/tabs.blade.php');

    expect($source)->toContain('data-live="tab-count:{{ $id }}:{{ $key }}"');
});
