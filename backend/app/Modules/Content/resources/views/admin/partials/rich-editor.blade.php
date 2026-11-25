{{--
    Rich text field for content bodies (blog posts, pages, FAQ answers): toolbar +
    contenteditable mirrored into a hidden textarea (resources/js/panels/admin/content.js).
    The server sanitises the HTML (allow-list). Inputs: $name, $label, $value, $dir,
    $required, $errorKey, $minHeight (Tailwind class).
--}}
@php
    $id = \App\Shared\Support\Ui::id($name);
    $commands = ['bold' => 'B', 'italic' => 'I', 'h2' => 'H2', 'h3' => 'H3', 'ul' => '•', 'ol' => '1.', 'quote' => '“', 'link' => null, 'unlink' => null];
@endphp
<div class="grid gap-1.5" data-field data-rich-editor>
    <span id="{{ $id }}-label" class="text-[13px] font-medium text-ink">{{ $label }}@if ($required ?? false)<span class="text-danger"> *</span>@endif</span>
    <div class="email-editor overflow-hidden rounded-control border border-line bg-field">
        <div class="flex flex-wrap gap-1 border-b border-line bg-subtle px-2 py-1.5" role="toolbar" aria-labelledby="{{ $id }}-label">
            @foreach ($commands as $command => $text)
                <button type="button" data-editor-command="{{ $command }}" class="inline-flex h-7 min-w-7 items-center justify-center rounded-chip px-1.5 text-xs font-medium text-ink-secondary hover:bg-surface hover:text-ink" title="{{ __('content::admin.common.editor.'.$command) }}" aria-label="{{ __('content::admin.common.editor.'.$command) }}">@if ($text){{ $text }}@else<x-ui::icon :name="$command === 'link' ? 'link' : 'x'" :size="14" />@endif</button>
            @endforeach
        </div>
        <div class="email-editor-content {{ $minHeight ?? 'min-h-56' }} px-3 py-3 text-sm text-ink outline-none" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="{{ $id }}-label" dir="{{ $dir ?? 'auto' }}" data-editor-area data-link-prompt="{{ __('content::admin.common.editor.link_prompt') }}">{!! $value !!}</div>
    </div>
    <textarea name="{{ $name }}" id="{{ $id }}" hidden data-editor-input>{{ $value }}</textarea>
    <p class="ui-error hidden text-xs text-danger" data-error-for="{{ $errorKey }}"></p>
</div>
