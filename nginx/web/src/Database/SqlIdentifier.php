<?php

declare(strict_types=1);

namespace TorStatus\Database;

final class SqlIdentifier
{
    public static function table(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)?$/', $identifier)) {
            \die_503('Invalid database table identifier');
        }

        $parts = explode('.', $identifier);
        return implode('.', array_map(static function (string $part): string {
            return '`' . str_replace('`', '``', $part) . '`';
        }, $parts));
    }
}
