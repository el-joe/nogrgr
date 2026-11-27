<?php

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use App\Modules\Notifications\Support\MailBodyStyler;

/*
| RQA-ADM-005: the call-to-action button of every templated e-mail must have a readable
| label (on-primary colour), not the primary colour of its own background.
*/

/**
 * @return array{background: string, color: string, label: string}
 */
function mailButtonOf(string $html): array
{
    preg_match('#<td align="center" style="border-radius:10px;background:([^;"]+);"><a ([^>]*)>(.*?)</a>#s', $html, $match);
    expect($match)->not->toBeEmpty();

    preg_match_all('/style="([^"]*)"/', $match[2], $styles);
    expect($styles[1])->toHaveCount(1); // one style attribute: a second one would be ignored by clients

    preg_match('/(?:^|;)color:([^;]+)/', $styles[1][0], $color);

    return ['background' => $match[1], 'color' => $color[1], 'label' => $match[3]];
}

it('renders the CTA label of a registry e-mail in the on-primary colour in English and Arabic', function (string $locale): void {
    $renderer = app(EmailTemplateRenderer::class);
    $rendered = $renderer->renderDefault(EmailTemplateEvent::AffiliateAlert, $locale, EmailTemplateEvent::AffiliateAlert->variables());

    $button = mailButtonOf($rendered->html);

    expect($button['color'])->not->toBe($button['background'])
        ->and($button['color'])->toBe(MailBodyStyler::onColor($button['background']))
        ->and(trim($button['label']))->not->toBe('');
})->with(['en', 'ar']);

it('renders the CTA label of edited preview content in a contrasting colour', function (): void {
    $rendered = app(EmailTemplateRenderer::class)->renderContent('Hi', '<p>Hello</p><p><a href="https://x.test/go">Open your dashboard</a></p>', 'en', []);

    $button = mailButtonOf($rendered->html);

    expect($button['label'])->toBe('Open your dashboard')
        ->and($button['color'])->not->toBe($button['background']);
});

it('picks white on dark brands and dark ink on light brands for central and store buttons', function (bool $centered): void {
    $orange = mailButtonOf(MailBodyStyler::style('<p><a href="https://x.test/a">Go</a></p>', '#ff5a2c', false, $centered));
    $yellow = mailButtonOf(MailBodyStyler::style('<p><a href="https://x.test/a">Go</a></p>', '#ffe14d', true, $centered));

    expect($orange['color'])->toBe('#ffffff')
        ->and($yellow['color'])->toBe('#2b1a14');
})->with(['central' => true, 'store' => false]);

it('keeps inline text links in the brand colour', function (): void {
    $html = MailBodyStyler::style('<p>Read <a href="https://x.test/a">the guide</a> first.</p>', '#ff5a2c');

    expect($html)->toContain('<a style="color:#ff5a2c;" href="https://x.test/a">the guide</a>');
});
