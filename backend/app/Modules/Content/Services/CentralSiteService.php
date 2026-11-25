<?php

namespace App\Modules\Content\Services;

use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Shared\Services\BaseService;
use Throwable;

/**
 * Platform contact channels for the central website (footer + contact page), read
 * from Admin › Settings › General (empty when the settings store is unreachable, e.g. on
 * the 500 / maintenance pages) (decision #69: settings come from the DB, never .env).
 */
class CentralSiteService extends BaseService
{
    /** @var array{contacts: list<array{key: string, icon: string, value: string, href: string}>, socials: list<array{network: string, url: string}>}|null */
    private ?array $channels = null;

    public function __construct(private readonly GeneralSettingsService $settings) {}

    /**
     * @return array{contacts: list<array{key: string, icon: string, value: string, href: string}>, socials: list<array{network: string, url: string}>}
     */
    public function channels(): array
    {
        if ($this->channels !== null) {
            return $this->channels;
        }

        try {
            $current = $this->settings->current();
        } catch (Throwable $exception) {
            report($exception);

            return $this->channels = ['contacts' => [], 'socials' => []];
        }

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
                $socials[] = ['network' => (string) $network, 'url' => $url];
            }
        }

        return $this->channels = ['contacts' => $contacts, 'socials' => $socials];
    }
}
