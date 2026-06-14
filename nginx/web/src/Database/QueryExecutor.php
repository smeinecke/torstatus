<?php

declare(strict_types=1);

namespace TorStatus\Database;

use TorStatus\Cache\CacheInterface;
use TorStatus\Http\Response;

final class QueryExecutor
{
    /** @var \mysqli */
    private $mysqli;

    /** @var CacheInterface */
    private $cache;

    public function __construct(\mysqli $mysqli, CacheInterface $cache)
    {
        $this->mysqli = $mysqli;
        $this->cache = $cache;
    }

    /** @param array<int, mixed> $params */
    public function result(string $query, array $params = [], string $errorMessage = 'Query failed'): \mysqli_result
    {
        $stmt = $this->prepareAndExecute($query, $params, $errorMessage);
        $result = $stmt->get_result();
        $stmt->close();

        if (!$result instanceof \mysqli_result) {
            Response::serviceUnavailable($errorMessage . ': no result set returned');
        }

        return $result;
    }

    /** @param array<int, mixed> $params */
    public function execute(string $query, array $params = [], string $errorMessage = 'Query failed'): void
    {
        $stmt = $this->prepareAndExecute($query, $params, $errorMessage);
        $stmt->close();
    }

    /**
     * @param array<int, mixed> $params
     * @return array<string, mixed>
     */
    public function singleRow(string $query, array $params = [], int $cacheExpiration = -1, string $errorMessage = 'Query failed'): array
    {
        $cacheKey = null;
        if ($cacheExpiration > -1) {
            $cacheKey = 'torstatus_query_' . sha1($query . "\0" . serialize($params));
            $cached = $this->cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $record = unserialize($cached, ['allowed_classes' => false]);
                if (is_array($record)) {
                    return $record;
                }
            }
        }

        $result = $this->result($query, $params, $errorMessage);
        $record = $result->fetch_assoc();
        $result->free();

        $row = is_array($record) ? $record : [];
        if ($cacheKey !== null) {
            $this->cache->set($cacheKey, serialize($row), $cacheExpiration);
        }

        return $row;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $query, array $params = [], int $cacheExpiration = -1, string $errorMessage = 'Query failed'): array
    {
        $cacheKey = null;
        if ($cacheExpiration > -1) {
            $cacheKey = 'torstatus_query_rows_' . sha1($query . "\0" . serialize($params));
            $cached = $this->cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $rows = unserialize($cached, ['allowed_classes' => false]);
                if (is_array($rows)) {
                    return $rows;
                }
            }
        }

        $result = $this->result($query, $params, $errorMessage);
        $rows = [];
        while ($record = $result->fetch_assoc()) {
            $rows[] = $record;
        }
        $result->free();

        if ($cacheKey !== null) {
            $this->cache->set($cacheKey, serialize($rows), $cacheExpiration);
        }

        return $rows;
    }

    /** @param array<int, mixed> $params */
    private function prepareAndExecute(string $query, array $params, string $errorMessage): \mysqli_stmt
    {
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            Response::serviceUnavailable($errorMessage . ': ' . $this->mysqli->error);
        }

        if ($params !== []) {
            $types = '';
            $values = [];
            foreach ($params as $param) {
                $types .= $this->parameterType($param);
                $values[] = $param;
            }
            $refs = [];
            foreach ($values as $key => &$value) {
                $refs[$key] =& $value;
            }
            $stmt->bind_param($types, ...$refs);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error ?: $this->mysqli->error;
            $stmt->close();
            Response::serviceUnavailable($errorMessage . ': ' . $error);
        }

        return $stmt;
    }

    private function parameterType($value): string
    {
        if (is_int($value) || is_bool($value)) {
            return 'i';
        }
        if (is_float($value)) {
            return 'd';
        }

        return 's';
    }
}
