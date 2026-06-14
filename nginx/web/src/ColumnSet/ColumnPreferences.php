<?php

declare(strict_types=1);

namespace TorStatus\ColumnSet;

final class ColumnPreferences
{
    public const COLUMNS = [
        'Fingerprint', 'CountryCode', 'Bandwidth', 'Uptime', 'LastDescriptorPublished',
        'Hostname', 'IP', 'ORPort', 'DirPort', 'Platform', 'Contact', 'Authority',
        'BadDir', 'BadExit', 'Exit', 'Fast', 'Guard', 'Hibernating', 'Named', 'Stable',
        'Running', 'Valid', 'V2Dir', 'HSDir',
    ];

    /** @var array<int, string> */
    private $active;

    /** @var array<int, string> */
    private $inactive;

    /** @param array<int, string> $active
     *  @param array<int, string> $inactive
     */
    public function __construct(array $active, array $inactive)
    {
        $this->active = $this->normalizeColumnList($active);
        $this->inactive = $this->normalizeColumnList($inactive);
    }

    /** @param array<int, string> $defaultActive
     *  @param array<int, string> $defaultInactive
     *  @param array<string, mixed> $session
     */
    public static function fromSession(array $defaultActive, array $defaultInactive, array $session): self
    {
        if (!isset($session['ColumnSetVisited']) && !isset($session['IndexVisited'])) {
            return new self($defaultActive, $defaultInactive);
        }

        return new self(
            self::arrayOfStrings($session['ColumnList_ACTIVE'] ?? []),
            self::arrayOfStrings($session['ColumnList_INACTIVE'] ?? [])
        );
    }

    /** @param array<string, mixed> $post */
    public function applyPost(array $post): ColumnSetAction
    {
        $selectedActive = $this->sanitizeColumn($post['CR_ACTIVE'] ?? null);
        $selectedInactive = $this->sanitizeColumn($post['CR_INACTIVE'] ?? null);
        $action = ColumnSetAction::fromPost($post, $selectedActive, $selectedInactive);

        if ($action->add && $selectedInactive !== null) {
            $this->moveBetweenLists($selectedInactive, $this->inactive, $this->active);
        } elseif ($action->remove && $selectedActive !== null) {
            $this->moveBetweenLists($selectedActive, $this->active, $this->inactive);
        } elseif ($action->up && $selectedActive !== null) {
            $this->active = $this->move($this->active, $selectedActive, -1);
        } elseif ($action->down && $selectedActive !== null) {
            $this->active = $this->move($this->active, $selectedActive, 1);
        }

        return $action;
    }

    /** @param array<string, mixed> $session */
    public function persist(array &$session): void
    {
        $session['ColumnList_ACTIVE'] = $this->active;
        $session['ColumnList_INACTIVE'] = $this->inactive;
        $session['ColumnSetVisited'] = 1;
    }

    /** @return array<int, string> */
    public function active(): array
    {
        return $this->active;
    }

    /** @return array<int, string> */
    public function inactive(): array
    {
        return $this->inactive;
    }

    private function sanitizeColumn($value): ?string
    {
        if (!is_string($value) || !in_array($value, self::COLUMNS, true)) {
            return null;
        }

        return $value;
    }

    /** @param array<int, string> $source
     *  @param array<int, string> $target
     */
    private function moveBetweenLists(string $column, array &$source, array &$target): void
    {
        $key = array_search($column, $source, true);
        if ($key === false) {
            return;
        }

        unset($source[$key]);
        $source = array_values($source);
        if (!in_array($column, $target, true)) {
            $target[] = $column;
        }
    }

    /** @param array<int, string> $columns
     *  @return array<int, string>
     */
    private function move(array $columns, string $column, int $offset): array
    {
        $index = array_search($column, $columns, true);
        if ($index === false) {
            return array_values($columns);
        }

        $newIndex = $index + $offset;
        if ($newIndex < 0 || $newIndex >= count($columns)) {
            return array_values($columns);
        }

        [$item] = array_splice($columns, $index, 1);
        array_splice($columns, $newIndex, 0, [$item]);

        return $columns;
    }

    /** @param array<int, string> $columns
     *  @return array<int, string>
     */
    private function normalizeColumnList(array $columns): array
    {
        $seen = [];
        $normalized = [];
        foreach ($columns as $column) {
            if (!in_array($column, self::COLUMNS, true) || isset($seen[$column])) {
                continue;
            }
            $seen[$column] = true;
            $normalized[] = $column;
        }

        return $normalized;
    }

    /** @return array<int, string> */
    private static function arrayOfStrings($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static function ($item): bool {
            return is_string($item) && $item !== '';
        }));
    }
}
