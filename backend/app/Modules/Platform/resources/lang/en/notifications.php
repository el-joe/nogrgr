<?php

return [
    'provisioning_failed' => [
        'title' => 'Store provisioning failed',
        'body' => ':store could not be provisioned. Open the store to see the error and retry.',
    ],
    'store_provisioned' => [
        'title' => 'New store is live',
        'body' => ':store finished provisioning and is active.',
    ],
    'compliance_submitted' => [
        'title' => 'New compliance submission',
        'body' => ':store submitted its compliance documents for review.',
    ],
    'compliance_resubmitted' => [
        'title' => 'Compliance re-submitted',
        'body' => ':store updated its compliance details and re-submitted them for review.',
    ],
    'owner_compliance' => [
        'title' => 'Compliance review: :status',
        'body' => 'The compliance review of :store is now :status.',
    ],
    'owner_domain_dns_failed' => [
        'title' => 'Domain :domain needs attention',
        'body' => 'The DNS records of :domain (:store) were not found in the last check. Visitors may not reach the store on it.',
    ],
    'admin_domain_dns_failed' => [
        'title' => 'DNS check failed: :domain',
        'body' => 'The DNS records of :domain (:store) are missing after being found before.',
    ],
];
