<?php

namespace App\Shared\Http;

/**
 * How a remote-modal fragment is framed when its URL is opened directly (new tab,
 * bookmark, reload): panel shell, page header, card with the fragment, and — for a
 * form — Cancel / submit buttons. After a successful submit the page goes to $backUrl.
 */
final readonly class RemoteModalPage
{
    /**
     * @param  array<string, string|null>  $breadcrumbs  label => url (null = current)
     */
    public function __construct(
        public string $surface,
        public string $title,
        public string $backUrl,
        public ?string $pageModule = null,
        public ?string $formId = null,
        public ?string $submitLabel = null,
        public array $breadcrumbs = [],
    ) {}
}
