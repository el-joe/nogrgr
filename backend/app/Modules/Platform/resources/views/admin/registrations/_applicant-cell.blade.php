{{-- Pending registrations › Applicant: name (or e-mail local part) + e-mail, links to the detail. --}}
<a href="{{ route('admin.vendors.pending.show', $registration->id) }}" class="grid min-w-0 hover:underline">
    <span class="truncate font-medium text-ink">{{ $registration->applicantName() }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $registration->email }}</span>
</a>
