<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\StoreMailSettingsData;
use App\Modules\Notifications\Mail\StoreTestMail;
use App\Modules\Notifications\Support\StoreMailerResolver;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Vendor › Settings › Mail (VEN-081, tenant `store_settings`, never .env — decision #69):
 * the store's SMTP server. Once saved, every e-mail sent for this store goes through it
 * (StoreAwareMailManager); empty = platform mail. The password is stored encrypted and
 * never sent back to the page.
 */
class StoreMailSettingsService
{
    public function __construct(
        private readonly StoreSettingRepositoryContract $settings,
        private readonly StoreMailerResolver $resolver,
    ) {}

    /**
     * Saved values without the password (+ has_password).
     *
     * @return array{host: string, port: int, encryption: string, username: string, from_address: string, from_name: string, has_password: bool, configured: bool}
     */
    public function current(): array
    {
        $stored = $this->stored();

        return [
            'host' => (string) ($stored['host'] ?? ''),
            'port' => (int) ($stored['port'] ?? 587),
            'encryption' => (string) ($stored['encryption'] ?? 'tls'),
            'username' => (string) ($stored['username'] ?? ''),
            'from_address' => (string) ($stored['from_address'] ?? ''),
            'from_name' => (string) ($stored['from_name'] ?? ''),
            'has_password' => is_string($stored['password'] ?? null) && $stored['password'] !== '',
            'configured' => StoreMailerResolver::toMailerConfig($stored) !== null,
        ];
    }

    public function save(StoreMailSettingsData $data): void
    {
        $this->settings->put(StoreSettingKey::MailSmtp, $this->toSetting($data, $this->stored()));
        $this->resolver->forget();
    }

    /**
     * Back to the platform mail.
     */
    public function clear(): void
    {
        $this->settings->forget(StoreSettingKey::MailSmtp);
        $this->resolver->forget();
    }

    /**
     * Sends the test e-mail with the values on the form (saved password when left
     * empty) and returns the delivery time in seconds.
     */
    public function sendTest(StoreMailSettingsData $data, string $recipient, string $storeName): float
    {
        $stored = $this->stored();
        $config = StoreMailerResolver::toMailerConfig(
            $this->toSetting($data, $stored),
            $data->password ?? StoreMailerResolver::decrypt($stored['password'] ?? null),
        );

        if ($config === null) {
            throw new DomainException(__('notifications::vendor_mail.test_incomplete'));
        }

        // A throw-away named mailer (same as Admin › Integrations › Mail "Send test").
        $name = 'store_smtp_test';
        config(['mail.mailers.'.$name => $config]);
        Mail::purge($name);
        $started = microtime(true);

        try {
            Mail::mailer($name)->to($recipient)->send((new StoreTestMail($storeName))->from($config['from']['address'], $config['from']['name']));
        } catch (TransportExceptionInterface $exception) {
            throw new DomainException(__('notifications::vendor_mail.test_failed', ['error' => $exception->getMessage()]));
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new DomainException(__('notifications::vendor_mail.test_failed', ['error' => class_basename($exception)]));
        } finally {
            Mail::purge($name);
            config(['mail.mailers.'.$name => null]);
        }

        return round(microtime(true) - $started, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $value = $this->settings->get(StoreSettingKey::MailSmtp);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function toSetting(StoreMailSettingsData $data, array $stored): array
    {
        return [
            'host' => $data->host,
            'port' => $data->port,
            'encryption' => $data->encryption->value,
            'username' => $data->username,
            'password' => $data->password !== null ? Crypt::encryptString($data->password) : ($stored['password'] ?? null),
            'from_address' => $data->fromAddress,
            'from_name' => $data->fromName,
        ];
    }
}
