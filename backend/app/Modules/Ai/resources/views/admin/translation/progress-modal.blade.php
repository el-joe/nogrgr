{{--
    Progress modal of admin AI translation runs (ADM-091b / ADM-092a), opened by
    [data-ai-run-open] (page banner), after a run starts, or by a retry. Body = the run's
    status HTML ([data-ai-run-body]); live settings in the JSON island
    (Reverb channel admin.translation-runs + poll fallback), read by
    resources/js/panels/admin/ai-translation-runs.js.
--}}
<x-ui::modal id="ai-run-modal" :title="__('ai::admin_translation.progress.title')" size="md">
    <div data-ai-run-loading class="grid gap-4" aria-busy="true">
        <x-ui::skeleton shape="rect" class="h-8 w-full" />
        <x-ui::skeleton shape="rect" class="h-8 w-full" />
        <x-ui::skeleton shape="rect" class="h-8 w-2/3" />
    </div>
    <div data-ai-run-error hidden>
        <x-ui::empty-state compact variant="error" :description="__('ai::admin_translation.progress.load_error')" />
    </div>
    <div data-ai-run-body aria-live="polite" hidden></div>
</x-ui::modal>
<script type="application/json" data-ai-run-live>@json(\App\Modules\Ai\Support\AdminTranslationLive::config())</script>
