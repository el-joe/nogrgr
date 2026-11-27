<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use App\Modules\Storefront\Support\CustomThemes\CustomThemeLimits;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Render limits of custom themes (decision #121): a wall-clock budget, a tick budget
 * (loop iterations + template / block / macro calls) and a call-depth guard, enforced by
 * RenderBudgetNode ticks that RenderBudgetVisitor compiles into every template.
 * `start()` arms it before each page render.
 */
final class RenderBudgetExtension extends AbstractExtension
{
    private float $startedAt = 0.0;

    private int $ticks = 0;

    private int $depth = 0;

    public function __construct(
        private readonly int $maxMilliseconds = CustomThemeLimits::RENDER_MAX_MS,
        private readonly int $maxTicks = CustomThemeLimits::RENDER_MAX_TICKS,
        private readonly int $maxDepth = CustomThemeLimits::RENDER_MAX_DEPTH,
    ) {}

    /**
     * @return list<NodeVisitorInterface>
     */
    public function getNodeVisitors(): array
    {
        return [new RenderBudgetVisitor];
    }

    public function start(): void
    {
        $this->startedAt = hrtime(true) / 1e6;
        $this->ticks = 0;
        $this->depth = 0;
    }

    public function tick(string $kind): void
    {
        if ($kind === 'leave') {
            $this->depth = max(0, $this->depth - 1);

            return;
        }

        $this->ticks++;

        if ($this->ticks > $this->maxTicks) {
            throw new RuntimeError(sprintf('The theme exceeded the render budget of %d loop iterations / calls.', $this->maxTicks));
        }

        if ($this->startedAt > 0 && (hrtime(true) / 1e6) - $this->startedAt > $this->maxMilliseconds) {
            throw new RuntimeError(sprintf('The theme took longer than %d ms to render.', $this->maxMilliseconds));
        }

        if ($kind === 'enter' && ++$this->depth > $this->maxDepth) {
            throw new RuntimeError(sprintf('The theme nests templates, blocks or macros deeper than %d levels (recursion?).', $this->maxDepth));
        }
    }
}
