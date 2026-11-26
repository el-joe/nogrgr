<?php

namespace App\Modules\Notifications\Support;

use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;

use function Illuminate\Support\enum_value;

/**
 * Mail manager that sends a store's e-mails through the store's own SMTP (VEN-081,
 * decision #91): inside a store (tenant context) with a saved SMTP setup, the default
 * mailer is that store's mailer (its own "from"); a named mailer, central context or a
 * store without SMTP keep the platform mail settings. Covers Mail::to(), mailables,
 * queued mail and notification mail (they all resolve mailer(null)).
 */
class StoreAwareMailManager extends MailManager
{
    public function mailer($name = null)
    {
        if (blank(enum_value($name)) && $this->app->bound(StoreMailerResolver::class)) {
            $config = $this->app->make(StoreMailerResolver::class)->config();

            if ($config !== null) {
                $key = 'store-smtp-'.tenant()?->getTenantKey().'-'.substr(sha1(serialize($config)), 0, 12);

                return $this->mailers[$key] ??= $this->buildStoreMailer($key, $config);
            }
        }

        return parent::mailer($name);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildStoreMailer(string $name, array $config): Mailer
    {
        $mailer = $this->build(['name' => $name, ...$config]);
        $this->setGlobalAddress($mailer, $config, 'from');

        return $mailer;
    }
}
