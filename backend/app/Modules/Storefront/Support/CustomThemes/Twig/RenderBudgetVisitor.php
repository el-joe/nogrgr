<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use App\Modules\Storefront\Support\CustomThemes\CustomThemeLimits;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\Expression\Binary\RangeBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\ForNode;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Adds a RenderBudgetNode tick to every loop body and enter / leave ticks around every
 * template display, block and macro,
 * and only accepts literal ranges (`1..12`) of at most CustomThemeLimits::MAX_RANGE items.
 */
final class RenderBudgetVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        // `range()` is only reachable through the literal `a..b` operator (the sandbox needs
        // "range" allowed for it); the function form could build huge arrays from variables.
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'range') {
            throw new SyntaxError('Use a literal range such as 1..5 instead of range().', $node->getTemplateLine(), $node->getSourceContext());
        }

        if ($node instanceof RangeBinary) {
            $left = $node->getNode('left');
            $right = $node->getNode('right');

            if (! $left instanceof ConstantExpression || ! $right instanceof ConstantExpression || ! is_int($left->getAttribute('value')) || ! is_int($right->getAttribute('value'))) {
                throw new SyntaxError('Ranges must use literal whole numbers (e.g. 1..5).', $node->getTemplateLine(), $node->getSourceContext());
            }

            if (abs($right->getAttribute('value') - $left->getAttribute('value')) >= CustomThemeLimits::MAX_RANGE) {
                throw new SyntaxError(sprintf('Ranges may hold at most %d items.', CustomThemeLimits::MAX_RANGE), $node->getTemplateLine(), $node->getSourceContext());
            }
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ForNode) {
            $node->setNode('body', new Nodes([new RenderBudgetNode('loop', $node->getTemplateLine()), $node->getNode('body')]));
        } elseif ($node instanceof ModuleNode) {
            $node->setNode('display_start', new Nodes([new RenderBudgetNode('enter', $node->getTemplateLine()), $node->getNode('display_start')]));
            $node->setNode('display_end', new Nodes([$node->getNode('display_end'), new RenderBudgetNode('leave', $node->getTemplateLine())]));
        } elseif ($node instanceof BlockNode || $node instanceof MacroNode) {
            $line = $node->getTemplateLine();
            $node->setNode('body', new Nodes([new RenderBudgetNode('enter', $line), $node->getNode('body'), new RenderBudgetNode('leave', $line)]));
        }

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
