<?php

use App\Modules\Notifications\Support\MailPlainText;

/*
| Plain-text alternative of every e-mail (decision #124): built from the rendered HTML,
| hidden preheader / styles dropped, detail rows "label · value", links "label (url)".
*/

it('turns rendered e-mail HTML into readable text', function (): void {
    $html = '<html><head><style>p{color:red}</style><title>x</title></head><body>'
        .'<div style="display:none">Preheader text</div>'
        .'<table><tr><td><h2>Hello Sara</h2><p>Your order&nbsp;is <strong>ready</strong>.</p>'
        .'<table><tr><td>Order</td><td>ORD-1</td></tr><tr><td>Total</td><td>$12.00</td></tr></table>'
        .'<p><a href="https://shop.test/orders/1">View your order</a></p>'
        .'<p><a href="https://shop.test">https://shop.test</a></p><ul><li>One</li><li>Two</li></ul></td></tr></table>'
        .'</body></html>';

    $text = MailPlainText::fromHtml($html);

    expect($text)->toContain('Hello Sara')
        ->toContain('Your order is ready.')
        ->toContain('Order · ORD-1')
        ->toContain('Total · $12.00')
        ->toContain('View your order (https://shop.test/orders/1)')
        ->toContain('https://shop.test')
        ->toContain('- One')
        ->not->toContain('Preheader text')
        ->not->toContain('color:red')
        ->not->toContain('<');
});

it('returns an empty string for empty HTML', function (): void {
    expect(MailPlainText::fromHtml('  '))->toBe('');
});
