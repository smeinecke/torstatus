<?php

declare(strict_types=1);

namespace TorStatus\Template;

use Twig\Environment;

final class Renderer
{
    /** @var Environment */
    private $twig;

    /** @var array<string, mixed> */
    private $defaultContext;

    /**
     * @param array<string, mixed> $defaultContext
     */
    public function __construct(Environment $twig, array $defaultContext)
    {
        $this->twig = $twig;
        $this->defaultContext = $defaultContext;
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): void
    {
        echo $this->twig->render($template, array_merge($this->defaultContext, $context));
    }
}
