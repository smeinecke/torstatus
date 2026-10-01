<?php

declare(strict_types=1);

namespace TorStatus\Template;

use Twig\Environment;

final class Renderer
{
    /**
     * @param array<string, mixed> $defaultContext
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly array $defaultContext,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): void
    {
        echo $this->twig->render($template, array_merge($this->defaultContext, $context));
    }
}
