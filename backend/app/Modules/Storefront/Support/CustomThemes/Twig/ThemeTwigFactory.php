<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;

/**
 * Builds the sandboxed Twig environment of one theme version (decision #121):
 *
 *  - ArrayLoader over the package's own templates only (names relative to templates/,
 *    e.g. "layout.twig", "partials/card.twig"): no filesystem, no other theme, no app view;
 *  - SandboxExtension with ThemeSecurityPolicy, sandboxed globally (every template);
 *  - autoescape "html" (cannot be switched off: `autoescape` is not an allowed tag);
 *  - RenderBudgetExtension (time / ticks / depth, literal ranges only) and ThemeExtension;
 *  - compiled-template cache per tenant / version (or none, for validation).
 */
final class ThemeTwigFactory
{
    /**
     * @param  array<string, string>  $templates  name => source
     */
    public static function make(array $templates, ThemeRuntime $runtime, string|false $cache = false): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), [
            'autoescape' => 'html',
            'cache' => $cache,
            'auto_reload' => false,
            'debug' => false,
            'strict_variables' => false,
            'charset' => 'UTF-8',
            'optimizations' => -1,
            'use_yield' => true,
        ]);

        $twig->addExtension(new SandboxExtension(ThemeSecurityPolicy::make(), true));
        $twig->addExtension(new RenderBudgetExtension);
        $twig->addExtension(new ThemeExtension($runtime));

        return $twig;
    }

    /**
     * Render one template under a fresh render budget.
     *
     * @param  array<string, mixed>  $context
     */
    public static function render(Environment $twig, string $template, array $context): string
    {
        $twig->getExtension(RenderBudgetExtension::class)->start();

        return $twig->render($template, $context);
    }
}
