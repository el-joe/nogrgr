<?php

namespace App\Modules\Engagement\Services;

use App\Modules\Engagement\Enums\SubscriberSource;
use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Shared\Support\Locale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Newsletter subscribers (VEN-026): the store's newsletter list (tenant DB) —
 * unsubscribe / re-subscribe, delete and the CSV export of the filtered list.
 */
class VendorSubscriberService
{
    public function __construct(private readonly NewsletterSubscriberRepositoryContract $subscribers) {}

    /**
     * @throws ModelNotFoundException
     */
    public function find(int $id): NewsletterSubscriber
    {
        /** @var NewsletterSubscriber|null $subscriber */
        $subscriber = $this->subscribers->find($id);

        return $subscriber ?? throw (new ModelNotFoundException)->setModel(NewsletterSubscriber::class, [$id]);
    }

    /**
     * Language filter options: the panel languages plus any other language present.
     *
     * @return array<string, string>
     */
    public function localeOptions(): array
    {
        $options = collect(Locale::enabled())->map(static fn (array $locale): string => $locale['native'])->all();

        foreach ($this->subscribers->locales() as $locale) {
            $options[$locale] ??= strtoupper($locale);
        }

        return $options;
    }

    public function setSubscribed(NewsletterSubscriber $subscriber, bool $subscribed): NewsletterSubscriber
    {
        /** @var NewsletterSubscriber $subscriber */
        $subscriber = $this->subscribers->update($subscriber, $subscribed
            ? ['unsubscribed_at' => null, 'subscribed_at' => now()]
            : ['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()]);

        return $subscriber;
    }

    public function delete(NewsletterSubscriber $subscriber): void
    {
        $this->subscribers->delete($subscriber);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $query = $this->subscribers->listing($filters)->orderByDesc('id');

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['email', 'language', 'source', 'status', 'subscribed_at', 'unsubscribed_at']);

            $query->chunk(500, static function ($subscribers) use ($out): void {
                /** @var NewsletterSubscriber $subscriber */
                foreach ($subscribers as $subscriber) {
                    fputcsv($out, [
                        $subscriber->email,
                        (string) $subscriber->locale,
                        SubscriberSource::forCustomerId($subscriber->customer_id)->value,
                        $subscriber->unsubscribed_at === null ? 'subscribed' : 'unsubscribed',
                        $subscriber->subscribed_at->toDateTimeString(),
                        (string) $subscriber->unsubscribed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
