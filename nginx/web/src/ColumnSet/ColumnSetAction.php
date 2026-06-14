<?php

declare(strict_types=1);

namespace TorStatus\ColumnSet;

final class ColumnSetAction
{
    /** @var string|null */
    public $selectedActive;

    /** @var string|null */
    public $selectedInactive;

    /** @var string|null */
    public $add;

    /** @var string|null */
    public $remove;

    /** @var string|null */
    public $up;

    /** @var string|null */
    public $down;

    public function __construct(?string $selectedActive, ?string $selectedInactive, ?string $add, ?string $remove, ?string $up, ?string $down)
    {
        $this->selectedActive = $selectedActive;
        $this->selectedInactive = $selectedInactive;
        $this->add = $add;
        $this->remove = $remove;
        $this->up = $up;
        $this->down = $down;
    }

    /** @param array<string, mixed> $post */
    public static function fromPost(array $post, ?string $selectedActive, ?string $selectedInactive): self
    {
        return new self(
            $selectedActive,
            $selectedInactive,
            isset($post['Add']) ? (string)$post['Add'] : null,
            isset($post['Remove']) ? (string)$post['Remove'] : null,
            isset($post['Up']) ? (string)$post['Up'] : null,
            isset($post['Down']) ? (string)$post['Down'] : null
        );
    }

    /** @return array<string, mixed> */
    public function toTemplateContext(): array
    {
        return [
            'CR_ACTIVE' => $this->selectedActive,
            'CR_INACTIVE' => $this->selectedInactive,
            'CR_Add' => $this->add,
            'CR_Remove' => $this->remove,
            'CR_Up' => $this->up,
            'CR_Down' => $this->down,
        ];
    }
}
