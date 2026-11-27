<?php

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Sandbox\SecurityError;

require_once __DIR__.'/CustomThemeHelpers.php';

/*
| Custom Twig theme sandbox (decision #121): whitelisted tags / filters / functions /
| tests only, no PHP methods or properties, includes limited to the package, autoescape
| that cannot be switched off, and the render budget (ticks, depth, literal ranges).
*/

it('renders allowed Twig with autoescaping and the theme functions', function (): void {
    $html = sandboxRender([
        'layout.twig' => '<main>{% block body %}{% endblock %}</main>',
        'page.twig' => '{% extends "layout.twig" %}{% block body %}{% for p in products %}{{ p.name|upper }} {{ p.price_minor|money }} {% endfor %}{{ name }}{{ url("search", {q: "tee"}) }}{% include "partials/x.twig" %}{% endblock %}',
        'partials/x.twig' => '<i>{{ t("missing.key") }}</i>',
    ], context: ['products' => [['name' => 'tee', 'price_minor' => 1999]], 'name' => '<script>alert(1)</script>']);

    expect($html)->toContain('TEE $19.99')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)')
        ->toContain('/search?q=tee')
        ->toContain('<i>missing.key</i>');
});

it('blocks tags, filters, functions and tests outside the whitelist', function (string $source): void {
    expect(fn () => sandboxRender(['page.twig' => $source]))->toThrow(SecurityError::class);
})->with([
    'autoescape off' => ['{% autoescape false %}{{ x }}{% endautoescape %}'],
    'sandbox tag' => ['{% sandbox %}{% include "page.twig" %}{% endsandbox %}'],
    'do tag' => ['{% do 1 + 1 %}'],
    'raw filter' => ['{{ "<b>"|raw }}'],
    'constant function' => ['{{ constant("PHP_VERSION") }}'],
    'source function' => ['{{ source("page.twig") }}'],
    'attribute function' => ['{{ attribute(x, "y") }}'],
    'use tag' => ['{% use "page.twig" %}'],
    'constant test' => ['{% if x is constant("PHP_EOL") %}{% endif %}'],
]);

it('rejects unknown filters and functions at compile time', function (string $source): void {
    expect(fn () => sandboxRender(['page.twig' => $source]))->toThrow(SyntaxError::class);
})->with([
    'php function' => ['{{ system("id") }}'],
    'template_from_string' => ['{{ template_from_string("{{ 1 }}") }}'],
    'dump' => ['{{ dump() }}'],
    'unknown filter' => ['{{ x|exec }}'],
]);

it('never lets templates call methods or read properties of objects', function (): void {
    $object = new class
    {
        public string $secret = 'top-secret';

        public function leak(): string
        {
            return 'leaked';
        }
    };

    expect(fn () => sandboxRender(['page.twig' => '{{ obj.leak() }}'], context: ['obj' => $object]))->toThrow(SecurityError::class)
        ->and(fn () => sandboxRender(['page.twig' => '{{ obj.secret }}'], context: ['obj' => $object]))->toThrow(SecurityError::class);
});

it('only includes templates of the package itself', function (string $name): void {
    expect(fn () => sandboxRender(['page.twig' => '{% include "'.$name.'" %}']))->toThrow(LoaderError::class);
})->with(['../../../.env', '/etc/passwd', 'storefront::themes.ecommet.pages.home', '@App/x.twig', 'missing.twig']);

it('stops infinite recursion, endless loops and huge ranges', function (): void {
    expect(fn () => sandboxRender(['page.twig' => '{% macro r(n) %}{{ _self.r(n + 1) }}{% endmacro %}{{ _self.r(1) }}']))->toThrow(RuntimeError::class)
        ->and(fn () => sandboxRender(['page.twig' => '{% include "page.twig" %}']))->toThrow(RuntimeError::class)
        ->and(fn () => sandboxRender(['page.twig' => '{% for a in 1..400 %}{% for b in 1..400 %}.{% endfor %}{% endfor %}']))->toThrow(RuntimeError::class)
        ->and(fn () => sandboxRender(['page.twig' => '{% for a in 1..100000 %}{% endfor %}']))->toThrow(SyntaxError::class)
        ->and(fn () => sandboxRender(['page.twig' => '{% for a in 1..n %}{% endfor %}'], context: ['n' => 5]))->toThrow(SyntaxError::class)
        ->and(fn () => sandboxRender(['page.twig' => '{% for a in range(1, n) %}{% endfor %}'], context: ['n' => 5]))->toThrow(SyntaxError::class);

    expect(sandboxRender(['page.twig' => '{% for a in 1..3 %}{{ a }}{% endfor %}']))->toBe('123');
});

it('prints JSON islands safely with the json filter', function (): void {
    $html = sandboxRender(['page.twig' => '<script type="application/json">{{ data|json }}</script>'], context: ['data' => ['name' => '</script><b>"x"']]);

    expect($html)->not->toContain('</script><b>')
        ->and(json_decode(substr($html, strlen('<script type="application/json">'), -strlen('</script>')), true))->toBe(['name' => '</script><b>"x"']);
});
