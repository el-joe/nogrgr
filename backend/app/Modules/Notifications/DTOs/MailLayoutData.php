<?php

namespace App\Modules\Notifications\DTOs;

/**
 * What the shared e-mail layout (MAIL-001) draws for one e-mail: the central variant
 * (Admin › Email layout) or the store variant (store logo, brand colour, social links).
 */
final readonly class MailLayoutData
{
    /**
     * @param  list<array{label: string, url: string}>  $socials
     * @param  list<array{label: string, url: string}>  $links  footer links (help, privacy)
     */
    public function __construct(
        public string $brandName,
        public ?string $logoUrl,
        public ?string $homeUrl,
        public string $primaryColor,
        public string $backgroundColor,
        public string $footer,
        public array $socials,
        public bool $isStore,
        public string $address = '',
        public array $links = [],
        public string $label = '',
        public string $poweredBy = '',
    ) {}

    public function withLabel(string $label): self
    {
        return new self($this->brandName, $this->logoUrl, $this->homeUrl, $this->primaryColor, $this->backgroundColor, $this->footer, $this->socials, $this->isStore, $this->address, $this->links, $label, $this->poweredBy);
    }
}
