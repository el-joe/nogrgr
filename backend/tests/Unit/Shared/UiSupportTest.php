<?php

use App\Shared\Support\Icon;
use App\Shared\Support\Ui;

it('exports the Figma icons as currentColor SVGs', function (): void {
    expect(Icon::names())->toHaveCount(175)->toContain('dashboard', 'panel-left', 'openai', 'tp-home', 'tp-bell', 'elo-trash', 'elo-track-truck');

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

it('keeps the casing of a role name the store typed and headlines only slugs (QA-P8-007)', function (string $role, string $label): void {
    expect(Ui::roleLabel($role))->toBe($label);
})->with([
    ['QA Support', 'QA Support'],
    ['iOS Team', 'iOS Team'],
    ['packer', 'Packer'],
    ['support-agent', 'Support Agent'],
]);
