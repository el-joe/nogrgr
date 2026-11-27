<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use Twig\Sandbox\SecurityPolicy;

/**
 * The whitelist of the custom theme sandbox (decision #121). Every custom theme template
 * runs sandboxed globally: tags, filters, functions and tests not listed here fail at
 * compile / security-check time; no PHP method or property may be called (templates only
 * receive plain arrays and scalars, plus Markup for HTML we sanitised).
 */
final class ThemeSecurityPolicy
{
    /**
     * `autoescape`, `sandbox`, `do`, `flush`, `deprecated`, `use`, `guard`, `types`, `cache` are not allowed.
     */
    public const TAGS = ['apply', 'block', 'embed', 'extends', 'for', 'from', 'if', 'import', 'include', 'macro', 'set', 'verbatim', 'with'];

    /**
     * `raw`, `convert_encoding`, `format`-style printf of objects and `spaceless` are not allowed.
     */
    public const FILTERS = [
        'abs', 'batch', 'capitalize', 'column', 'date', 'default', 'e', 'escape', 'filter', 'first', 'join', 'json_encode', 'keys',
        'last', 'length', 'lower', 'map', 'merge', 'nl2br', 'number_format', 'replace', 'reverse', 'round', 'slice', 'sort', 'split',
        'striptags', 'title', 'trim', 'upper', 'url_encode',
        // ThemeExtension
        'money', 't', 'json',
    ];

    /**
     * Core functions allowed: block / parent (inheritance), include, cycle, max, min, date.
     * `attribute`, `constant`, `source`, `range()` (use `1..5`), `random`, `dump`, `template_from_string` are not allowed.
     */
    public const FUNCTIONS = [
        'block', 'parent', 'include', 'cycle', 'max', 'min', 'date',
        // Only for the literal `a..b` operator (RenderBudgetVisitor rejects range() calls).
        'range',
        // ThemeExtension
        'url', 'asset', 'script', 'money', 't', 'st', 'head', 'scripts', 'csrf_field', 'csrf_token', 'setting',
    ];

    /**
     * `constant` is not allowed.
     */
    public const TESTS = ['defined', 'divisible by', 'empty', 'even', 'iterable', 'none', 'null', 'odd', 'same as', 'mapping', 'sequence'];

    public static function make(): SecurityPolicy
    {
        // Strict: `extends`, `use`, `block`, `parent`, `attribute` and core tests are only
        // allowed when listed (Twig 4 behaviour).
        $policy = new SecurityPolicy(self::TAGS, self::FILTERS, [], [], self::FUNCTIONS, self::TESTS);
        $policy->setStrict(true);

        return $policy;
    }
}
