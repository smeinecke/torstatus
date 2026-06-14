<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class IndexRequest
{
    public const SORT_FIELDS = [
        'Name', 'Fingerprint', 'CountryCode', 'Bandwidth', 'Uptime',
        'LastDescriptorPublished', 'IP', 'Hostname', 'ORPort', 'DirPort',
        'Platform', 'Contact', 'FAuthority', 'FBadDirectory', 'FBadExit',
        'FExit', 'FFast', 'FGuard', 'Hibernating', 'FNamed', 'FStable',
        'FRunning', 'FValid', 'FV2Dir', 'FHSDir',
    ];

    public const FLAG_FIELDS = [
        'FAuthority', 'FBadDirectory', 'FBadExit', 'FExit', 'FFast',
        'FGuard', 'FHibernating', 'FNamed', 'FStable', 'FRunning',
        'FValid', 'FV2Dir', 'FHSDir',
    ];

    public const SEARCH_FIELDS = [
        'Fingerprint', 'Name', 'CountryCode', 'Bandwidth', 'Uptime',
        'LastDescriptorPublished', 'IP', 'Hostname', 'ORPort', 'DirPort',
        'Platform', 'Contact',
    ];

    public const SEARCH_MODIFIERS = ['Equals', 'Contains', 'LessThan', 'GreaterThan'];

    /** @var string */
    public $sortRequest;

    /** @var string */
    public $sortOrder;

    /** @var int */
    public $rowsPerPage;

    /** @var int */
    public $page;

    /** @var array<string, string> */
    public $filters;

    /** @var string */
    public $customSearchField;

    /** @var string */
    public $customSearchModifier;

    /** @var string|null */
    public $customSearchInput;

    /** @var array<int, string> */
    public $columnListActive;

    /** @var array<int, string> */
    public $columnListInactive;

    /**
     * @param array<string, string> $filters
     * @param array<int, string> $columnListActive
     * @param array<int, string> $columnListInactive
     */
    public function __construct(
        string $sortRequest,
        string $sortOrder,
        int $rowsPerPage,
        int $page,
        array $filters,
        string $customSearchField,
        string $customSearchModifier,
        ?string $customSearchInput,
        array $columnListActive,
        array $columnListInactive
    ) {
        $this->sortRequest = $sortRequest;
        $this->sortOrder = $sortOrder;
        $this->rowsPerPage = $rowsPerPage;
        $this->page = $page;
        $this->filters = $filters;
        $this->customSearchField = $customSearchField;
        $this->customSearchModifier = $customSearchModifier;
        $this->customSearchInput = $customSearchInput;
        $this->columnListActive = $columnListActive;
        $this->columnListInactive = $columnListInactive;
    }

    /**
     * @param array<int, string> $defaultActiveColumns
     * @param array<int, string> $defaultInactiveColumns
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     */
    public static function fromGlobals(array $defaultActiveColumns, array $defaultInactiveColumns, array $server, array $get, array $post, array $session): self
    {
        $method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST') {
            $sortRequest = self::stringFrom($post, 'SR');
            $sortOrder = self::stringFrom($post, 'SO');
        } elseif ($method === 'GET' && isset($get['SR'], $get['SO'])) {
            $sortRequest = self::stringFrom($get, 'SR');
            $sortOrder = self::stringFrom($get, 'SO');
        } else {
            $sortRequest = self::stringFrom($session, 'SR');
            $sortOrder = self::stringFrom($session, 'SO');
        }

        if ($method === 'POST') {
            $rowsPerPage = self::stringFrom($post, 'RowsPerPage');
            $page = self::stringFrom($post, 'Page');
        } elseif ($method === 'GET') {
            $rowsPerPage = self::stringFrom($get, 'RowsPerPage');
            $page = self::stringFrom($get, 'Page');
        } else {
            $rowsPerPage = self::stringFrom($session, 'RowsPerPage');
            $page = self::stringFrom($session, 'Page');
        }

        $filterSource = $method === 'POST' ? $post : ($method === 'GET' ? $get : $session);
        $filters = [];
        foreach (self::FLAG_FIELDS as $field) {
            $value = self::stringFrom($filterSource, $field);
            $filters[$field] = in_array($value, ['0', '1', 'OFF'], true) ? $value : 'OFF';
        }

        $customSearchField = self::stringFrom($filterSource, 'CSField');
        $customSearchModifier = self::stringFrom($filterSource, 'CSMod');
        $customSearchInput = self::stringFrom($filterSource, 'CSInput');

        $sortRequest = in_array($sortRequest, self::SORT_FIELDS, true) ? $sortRequest : 'Name';
        $sortOrder = in_array($sortOrder, ['Asc', 'Desc'], true) ? $sortOrder : 'Asc';
        $rowsPerPage = in_array($rowsPerPage, ['100', '500', '1000'], true) ? (int)$rowsPerPage : 100;

        $page = filter_var($page, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            $page = 1;
        }

        $customSearchField = in_array($customSearchField, self::SEARCH_FIELDS, true) ? $customSearchField : 'Fingerprint';
        $customSearchModifier = in_array($customSearchModifier, self::SEARCH_MODIFIERS, true) ? $customSearchModifier : 'Equals';
        if ($customSearchInput === '') {
            $customSearchInput = null;
        }
        if ($customSearchInput !== null && strlen($customSearchInput) > 128) {
            $customSearchInput = substr($customSearchInput, 0, 128);
        }

        $columnListActive = self::arrayOfStrings($session['ColumnList_ACTIVE'] ?? null);
        $columnListInactive = self::arrayOfStrings($session['ColumnList_INACTIVE'] ?? null);
        if (!isset($session['ColumnSetVisited']) && !isset($session['IndexVisited'])) {
            $columnListActive = $defaultActiveColumns;
            $columnListInactive = $defaultInactiveColumns;
        }

        return new self(
            $sortRequest,
            $sortOrder,
            $rowsPerPage,
            (int)$page,
            $filters,
            $customSearchField,
            $customSearchModifier,
            $customSearchInput,
            $columnListActive,
            $columnListInactive
        );
    }


    /** @return array<string, string> */
    public static function sortOptions(): array
    {
        return [
            'Name' => 'Router Name',
            'Fingerprint' => 'Fingerprint',
            'CountryCode' => 'Country Code',
            'Bandwidth' => 'Bandwidth',
            'Uptime' => 'Uptime',
            'LastDescriptorPublished' => 'Last Descriptor Published',
            'Hostname' => 'Hostname',
            'IP' => 'IP Address',
            'ORPort' => 'ORPort',
            'DirPort' => 'DirPort',
            'Platform' => 'Platform',
            'Contact' => 'Contact',
            'FAuthority' => 'Authority',
            'FBadDirectory' => 'Bad Directory',
            'FBadExit' => 'Bad Exit',
            'FExit' => 'Exit',
            'FFast' => 'Fast',
            'FGuard' => 'Guard',
            'Hibernating' => 'Hibernating',
            'FNamed' => 'Named',
            'FStable' => 'Stable',
            'FRunning' => 'Running',
            'FValid' => 'Valid',
            'FV2Dir' => 'V2Dir',
            'FHSDir' => 'HSDir',
        ];
    }

    /** @return array<string, string> */
    public static function filterOptions(): array
    {
        return [
            'FAuthority' => 'Authority',
            'FBadDirectory' => 'Bad Directory',
            'FBadExit' => 'BadExit',
            'FExit' => 'Exit',
            'FFast' => 'Fast',
            'FGuard' => 'Guard',
            'FHibernating' => 'Hibernating',
            'FNamed' => 'Named',
            'FStable' => 'Stable',
            'FRunning' => 'Running',
            'FValid' => 'Valid',
            'FV2Dir' => 'V2Dir',
            'FHSDir' => 'HSDir',
        ];
    }

    /** @return array<string, string> */
    public static function searchOptions(): array
    {
        return [
            'Fingerprint' => 'Fingerprint',
            'Name' => 'Router Name',
            'CountryCode' => 'Country Code',
            'Bandwidth' => 'Bandwidth (KB/s)',
            'Uptime' => 'Uptime (Days)',
            'LastDescriptorPublished' => 'Last Descriptor Published',
            'IP' => 'IP Address',
            'Hostname' => 'Hostname',
            'ORPort' => 'Onion Router Port',
            'DirPort' => 'Directory Server Port',
            'Platform' => 'Platform',
            'Contact' => 'Contact',
        ];
    }

    /** @return array<string, string> */
    public static function searchModifierOptions(): array
    {
        return [
            'Equals' => 'Equals',
            'Contains' => 'Contains',
            'LessThan' => 'Is Less Than',
            'GreaterThan' => 'Is Greater Than',
        ];
    }

    /** @param array<string, mixed> $session */
    public function persist(array &$session): void
    {
        $session['ColumnList_ACTIVE'] = $this->columnListActive;
        $session['ColumnList_INACTIVE'] = $this->columnListInactive;
        $session['SR'] = $this->sortRequest;
        $session['SO'] = $this->sortOrder;
        $session['RowsPerPage'] = (string)$this->rowsPerPage;
        $session['Page'] = $this->page;
        foreach (self::FLAG_FIELDS as $field) {
            $session[$field] = $this->filters[$field];
        }
        $session['CSField'] = $this->customSearchField;
        $session['CSMod'] = $this->customSearchModifier;
        $session['CSInput'] = $this->customSearchInput;
    }

    /** @return array<string, mixed> */
    public function toTemplateContext(): array
    {
        return array_merge($this->filters, [
            'columns_active' => $this->columnListActive,
            'sr' => $this->sortRequest,
            'so' => $this->sortOrder,
            'rows_per_page' => $this->rowsPerPage,
            'page' => $this->page,
            'CSField' => $this->customSearchField,
            'CSMod' => $this->customSearchModifier,
            'CSInput' => $this->customSearchInput,
        ]);
    }


    /** @return array<string, string> */
    public function toHiddenInputs(): array
    {
        $params = [
            'SR' => $this->sortRequest,
            'SO' => $this->sortOrder,
            'Page' => '1',
        ];
        foreach ($this->filters as $field => $value) {
            $params[$field] = $value;
        }
        if ($this->customSearchInput !== null) {
            $params['CSField'] = $this->customSearchField;
            $params['CSMod'] = $this->customSearchModifier;
            $params['CSInput'] = $this->customSearchInput;
        }

        return $params;
    }

    /** @return array<int, array{value: int, selected: bool}> */
    public function rowsPerPageOptions(): array
    {
        return array_map(function (int $value): array {
            return ['value' => $value, 'selected' => $this->rowsPerPage === $value];
        }, [100, 500, 1000]);
    }

    /** @return array<string, mixed> */
    public function pagination(string $self, int $page, int $totalPages): array
    {
        $baseQuery = $this->toBaseQuery();
        $url = static function (int $targetPage) use ($self, $baseQuery): string {
            return $self . '?' . $baseQuery . '&Page=' . $targetPage;
        };

        return [
            'page' => $page,
            'total_pages' => $totalPages,
            'first' => $page > 1 ? $url(1) : null,
            'prev' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $totalPages ? $url($page + 1) : null,
            'last' => $page < $totalPages ? $url($totalPages) : null,
        ];
    }

    public function toBaseUrl(string $self): string
    {
        $params = ['RowsPerPage' => (string)$this->rowsPerPage];
        foreach ($this->filters as $field => $value) {
            $params[$field] = $value;
        }
        if ($this->customSearchInput !== null) {
            $params['CSField'] = $this->customSearchField;
            $params['CSMod'] = $this->customSearchModifier;
            $params['CSInput'] = $this->customSearchInput;
        }
        return $self . '?' . http_build_query($params);
    }

    public function toBaseQuery(): string
    {
        $params = [
            'RowsPerPage' => (string)$this->rowsPerPage,
            'SR' => $this->sortRequest,
            'SO' => $this->sortOrder,
        ];
        foreach ($this->filters as $field => $value) {
            $params[$field] = $value;
        }
        if ($this->customSearchInput !== null) {
            $params['CSField'] = $this->customSearchField;
            $params['CSMod'] = $this->customSearchModifier;
            $params['CSInput'] = $this->customSearchInput;
        }
        return http_build_query($params);
    }

    /** @param array<string, mixed> $source */
    private static function stringFrom(array $source, string $key): ?string
    {
        if (!array_key_exists($key, $source)) {
            return null;
        }
        $value = $source[$key];
        if (is_array($value)) {
            return null;
        }
        return (string)$value;
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
