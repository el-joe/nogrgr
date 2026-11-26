{{-- Shared fields of the new ticket form (VEN-091a, Figma 88:113398). $categories, $priorities. --}}
<x-ui::input name="subject" :label="__('support::tickets.fields.subject')" :placeholder="__('support::tickets.fields.subject_placeholder')" maxlength="200" required />
<div class="grid gap-4 sm:grid-cols-2">
    <x-ui::select name="category" :label="__('support::tickets.fields.category')" :options="$categories" :placeholder="__('support::tickets.fields.category')" :search="false" required />
    <x-ui::select name="priority" :label="__('support::tickets.fields.priority')" :options="$priorities" :selected="\App\Modules\Support\Enums\TicketPriority::Normal->value" :search="false" required />
</div>
<x-ui::textarea name="body" :label="__('support::tickets.fields.message')" :placeholder="__('support::tickets.fields.message_placeholder')" :maxlength="5000" :rows="5" required />
<x-ui::file-upload name="files" multiple :label="__('support::tickets.fields.files')" :hint="__('support::tickets.fields.files_hint')" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" />
