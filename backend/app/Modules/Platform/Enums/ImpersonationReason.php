<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ImpersonationReason: string
{
    use HasEnumHelpers;

    case SupportTicket = 'support_ticket';
    case Onboarding = 'onboarding';
    case BugInvestigation = 'bug_investigation';
    case ComplianceReview = 'compliance_review';
    case Other = 'other';
}
