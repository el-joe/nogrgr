<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Compiled into every loop body, template display, block and macro of a custom theme:
 * one tick of the render budget (time, ticks) and, for calls, one level of depth.
 */
#[YieldReady]
final class RenderBudgetNode extends Node
{
    public function __construct(string $kind, int $lineno)
    {
        parent::__construct([], ['kind' => $kind], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->write('$this->env->getExtension(')
            ->string(RenderBudgetExtension::class)
            ->raw(')->tick(')
            ->string((string) $this->getAttribute('kind'))
            ->raw(");\n");
    }
}
