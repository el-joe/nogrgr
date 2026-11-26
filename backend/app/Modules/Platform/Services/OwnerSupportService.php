<?php

namespace App\Modules\Platform\Services;

use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Shared\Services\BaseService;

/**
 * Owner portal › Help & support (OWN-009): the platform's support channels as set in
 * Admin › Settings › General (ADM-101). Only configured channels are listed; support
 * tickets join in P9.
 */
class OwnerSupportService extends BaseService
{
    public function __construct(private readonly GeneralSettingsService $settings) {}

    /**
     * General settings `support_email` (decision #69), or '' when it is not set / invalid.
     * Never the mail "from" address (QA-P5-016).
     */
    public function supportEmail(): string
    {
        $email = trim((string) $this->settings->current()['support_email']);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    /**
     * @return array{contacts: list<array{key: string, icon: string, value: string, href: string}>, socials: list<array{network: string, url: string}>}
     */
    public function channels(): array
    {
        $current = $this->settings->current();
        $contacts = [];

        $email = trim((string) $current['support_email']);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $contacts[] = ['key' => 'email', 'icon' => 'mail', 'value' => $email, 'href' => 'mailto:'.$email];
        }

        $phone = trim((string) $current['support_phone']);
        $dial = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if ($dial !== '') {
            $contacts[] = ['key' => 'phone', 'icon' => 'message', 'value' => $phone, 'href' => 'tel:'.$dial];
        }

        $socials = [];

        foreach ($current['socials'] as $network => $url) {
            $url = trim((string) $url);

            if ($url !== '' && preg_match('#^https?://#i', $url) === 1) {
                $socials[] = ['network' => $network, 'url' => $url];
            }
        }

        return ['contacts' => $contacts, 'socials' => $socials];
    }
}
