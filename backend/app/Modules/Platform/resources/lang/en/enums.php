<?php

return [
    'tenant_status' => [
        'pending' => 'Pending',
        'provisioning' => 'Provisioning',
        'active' => 'Active',
        'failed' => 'Failed',
        'suspended' => 'Suspended',
        'trial' => 'Trial',
        'cancelled' => 'Cancelled',
    ],
    'domain_type' => [
        'subdomain' => 'Sub-domain',
        'custom' => 'Custom domain',
    ],
    'plan_assignment' => [
        'trial' => 'Start with the plan trial',
        'manual_paid' => 'Manual (mark as paid)',
        'invoiced' => 'Active now, invoice to pay',
    ],
    'registration_status' => [
        'payment_pending' => 'Payment pending',
        'payment_failed' => 'Payment failed',
        'email_unverified' => 'Email not verified',
        'incomplete' => 'Incomplete',
        'completed' => 'Completed',
    ],
    'registration_step' => [
        'payment' => 'Payment',
        'email_verification' => 'Email verification',
        'store_details' => 'Store details',
        'done' => 'Done',
    ],
    'compliance_status' => [
        'needs_review' => 'Needs review',
        'needs_action' => 'Needs action',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ],
    'compliance_risk' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],
    'compliance_document_type' => [
        'commercial_registration' => 'Commercial registration',
        'national_id' => 'National ID',
        'tax_certificate' => 'Tax certificate',
        'trade_licence' => 'Trade licence',
        'bank_letter' => 'Bank letter (IBAN)',
        'other' => 'Other document',
    ],
    'compliance_document_type_short' => [
        'commercial_registration' => 'CR',
        'national_id' => 'ID',
        'tax_certificate' => 'VAT',
        'trade_licence' => 'Trade licence',
        'bank_letter' => 'IBAN',
        'other' => 'Other',
    ],
    'compliance_document_status' => [
        'review' => 'Review',
        'verified' => 'Verified',
        'needs_action' => 'Needs action',
    ],
    'domain_request_status' => [
        'pending' => 'Pending',
        'connected' => 'Connected',
        'removed' => 'Removed',
    ],
    'dns_check_status' => [
        'unchecked' => 'Not checked',
        'ok' => 'All records OK',
        'missing' => 'Records missing',
        'error' => 'Check failed',
    ],
    'ssl_status' => [
        'unknown' => 'Unknown',
        'valid' => 'Valid',
        'invalid' => 'Invalid',
    ],
    'impersonation_reason' => [
        'support_ticket' => 'Support ticket',
        'onboarding' => 'Onboarding help',
        'bug_investigation' => 'Bug investigation',
        'compliance_review' => 'Compliance review',
        'other' => 'Other',
    ],
    'owner_store_state' => [
        'live' => 'Live',
        'trial' => 'Trial',
        'past_due' => 'Past due',
        'suspended' => 'Suspended',
        'not_launched' => 'Not launched',
    ],
];
