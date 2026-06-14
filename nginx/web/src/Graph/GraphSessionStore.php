<?php

declare(strict_types=1);

namespace TorStatus\Graph;

final class GraphSessionStore
{
    /** @param array<string, mixed> $session
     *  @param array<int, int|float|string> $data
     *  @param array<int, int|float|string> $labels
     */
    public static function put(array &$session, string $prefix, array $data, array $labels, string $title, ?string $legend = null): void
    {
        $session[$prefix . '_DATA_ARRAY_SERIALIZED'] = serialize(array_values($data));
        $session[$prefix . '_LABEL_ARRAY_SERIALIZED'] = serialize(array_values($labels));
        $session[$prefix . '_Title'] = $title;
        $session[$prefix . '_Legend'] = $legend;
    }

    /** @param array<string, mixed> $session
     *  @return array{data: array<int, mixed>, labels: array<int, mixed>, title: string, legend: string|null}
     */
    public static function get(array $session, string $prefix): array
    {
        foreach (['_DATA_ARRAY_SERIALIZED', '_LABEL_ARRAY_SERIALIZED', '_Title'] as $suffix) {
            if (!array_key_exists($prefix . $suffix, $session)) {
                http_response_code(400);
                die();
            }
        }

        $data = unserialize((string)$session[$prefix . '_DATA_ARRAY_SERIALIZED'], ['allowed_classes' => false]);
        $labels = unserialize((string)$session[$prefix . '_LABEL_ARRAY_SERIALIZED'], ['allowed_classes' => false]);

        if (!is_array($data) || !is_array($labels)) {
            http_response_code(400);
            die();
        }

        return [
            'data' => array_values($data),
            'labels' => array_values($labels),
            'title' => (string)$session[$prefix . '_Title'],
            'legend' => isset($session[$prefix . '_Legend']) ? (string)$session[$prefix . '_Legend'] : null,
        ];
    }
}
