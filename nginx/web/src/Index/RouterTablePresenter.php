<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class RouterTablePresenter
{
    /** @return array<int, array<string, mixed>> */
    public function headers(IndexRequest $request, string $baseUrl): array
    {
        $headers = [];
        foreach ($request->columnListActive as $column) {
            $headers[] = $this->header($column, $request, $baseUrl);
        }

        return $headers;
    }

    /** @return array<string, mixed> */
    public function nameHeader(IndexRequest $request, string $baseUrl): array
    {
        return [
            'class' => $this->sortedClass('Name', $request),
            'country_url' => $this->url($baseUrl, 'CountryCode', $request),
            'country_arrow' => $this->arrow('CountryCode', $request),
            'country_alt' => $this->arrowAlt('CountryCode', $request),
            'url' => $this->url($baseUrl, 'Name', $request),
            'arrow' => $this->arrow('Name', $request),
            'alt' => $this->arrowAlt('Name', $request),
            'label_html' => 'Router Name',
        ];
    }

    /** @return array<string, mixed> */
    private function header(string $column, IndexRequest $request, string $baseUrl): array
    {
        $labels = [
            'Fingerprint' => 'Fingerprint',
            'Bandwidth' => 'Bandwidth <span class="TRSM">(KB/s)</span>',
            'Uptime' => 'Uptime',
            'LastDescriptorPublished' => 'Last Descriptor<br/><span class="TRSM">(GMT)</span>',
            'Hostname' => 'Hostname',
            'ORPort' => 'ORPort',
            'DirPort' => 'DirPort',
            'Contact' => 'Contact',
            'IP' => 'IP Address',
            'Platform' => 'Platform',
            'Hibernating' => 'Hibernating',
            'Authority' => 'Authority',
            'Exit' => 'Exit',
            'Fast' => 'Fast',
            'Guard' => 'Guard',
            'Named' => 'Named',
            'Stable' => 'Stable',
            'Running' => 'Running',
            'Valid' => 'Valid',
            'V2Dir' => 'V2Dir',
            'HSDir' => 'HSDir',
            'BadDir' => 'Bad Dir',
            'BadExit' => 'Bad Exit',
        ];
        $sortKeys = [
            'Hibernating' => 'Hibernating',
            'Authority' => 'FAuthority',
            'Exit' => 'FExit',
            'Fast' => 'FFast',
            'Guard' => 'FGuard',
            'Named' => 'FNamed',
            'Stable' => 'FStable',
            'Running' => 'FRunning',
            'Valid' => 'FValid',
            'V2Dir' => 'FV2Dir',
            'HSDir' => 'FHSDir',
            'BadDir' => 'FBadDirectory',
            'BadExit' => 'FBadExit',
        ];
        $sortKey = $sortKeys[$column] ?? $column;

        return [
            'class' => $this->headerClass($sortKey, $column, $request),
            'url' => $this->url($baseUrl, $sortKey, $request),
            'arrow' => $this->arrow($sortKey, $request),
            'alt' => $this->arrowAlt($sortKey, $request),
            'label_html' => $labels[$column] ?? $column,
        ];
    }

    private function headerClass(string $sortKey, string $column, IndexRequest $request): string
    {
        $filterName = $column === 'BadDir' ? 'FBadDirectory' : ($column === 'BadExit' ? 'FBadExit' : null);
        if ($filterName !== null) {
            $filterValue = $request->filters[$filterName] ?? 'OFF';
            $sorted = $request->sortRequest === $filterName;
            if ($filterValue === '0') {
                return $sorted ? 'HRFNOS' : 'HRFNO';
            }
            if ($filterValue === '1') {
                return $sorted ? 'HRFYESS' : 'HRFYES';
            }
        }

        return $this->sortedClass($sortKey, $request);
    }

    private function sortedClass(string $sortKey, IndexRequest $request): string
    {
        return $request->sortRequest === $sortKey ? 'HRS' : 'HRN';
    }

    private function url(string $baseUrl, string $sortKey, IndexRequest $request): string
    {
        return $baseUrl . '&' . http_build_query([
            'SR' => $sortKey,
            'SO' => $this->nextSortOrder($sortKey, $request),
        ]);
    }

    private function arrow(string $sortKey, IndexRequest $request): string
    {
        return $request->sortRequest === $sortKey && $request->sortOrder === 'Asc' ? 'sortingarrowup.png' : 'sortingarrowdown.png';
    }

    private function arrowAlt(string $sortKey, IndexRequest $request): string
    {
        return $request->sortRequest === $sortKey && $request->sortOrder === 'Asc' ? '▲' : '▼';
    }

    private function nextSortOrder(string $sortKey, IndexRequest $request): string
    {
        return $request->sortRequest === $sortKey && $request->sortOrder === 'Asc' ? 'Desc' : 'Asc';
    }
}
