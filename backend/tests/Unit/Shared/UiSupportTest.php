<?php

use App\Shared\Support\Icon;
use App\Shared\Support\Ui;

it('exports the 73 Figma icons as currentColor SVGs', function (): void {
    expect(Icon::names())->toHaveCount(73)->toContain('dashboard', 'panel-left', 'openai');

    $svg = Icon::svg('chevron-right', 16, 'class="x"');

    expect($svg)->toContain('width="16"', 'stroke="currentColor"', 'aria-hidden="true"', 'data-flip', 'class="x"');
});

it('labels non-decorative icons and rejects unknown names', function (): void {
    expect(Icon::svg('bell', 20, '', 'Notifications'))->toContain('role="img"', 'aria-label="Notifications"');
    Icon::svg('../../etc/passwd');
})->throws(InvalidArgumentException::class);

it('maps field names to the dotted 422 error key', function (string $name, string $key): void {
    expect(Ui::errorKey($name))->toBe($key);
})->with([
    ['email', 'email'],
    ['title[en]', 'title.en'],
    ['items[0][qty]', 'items.0.qty'],
    ['countries[]', 'countries'],
]);

it('builds initials and sparkline points', function (): void {
    expect(Ui::initials('Sara Admin'))->toBe('SA')
        ->and(Ui::initials(''))->toBe('?')
        ->and(Ui::sparkline([1, 2, 3], 72, 24))->toBe('0,22 36,12 72,2')
        ->and(Ui::sparkline([], 72, 24))->toBe('0,12 72,12');
});
