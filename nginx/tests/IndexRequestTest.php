<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\TestCase;
use TorStatus\Index\IndexRequest;

final class IndexRequestTest extends TestCase
{
    public function testFromGlobalsDefaults(): void
    {
        $request = IndexRequest::fromGlobals(
            ['Name', 'CountryCode'],
            ['Contact'],
            ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/index.php'],
            [],
            [],
            []
        );

        self::assertSame('Name', $request->sortRequest);
        self::assertSame('Asc', $request->sortOrder);
        self::assertSame(100, $request->rowsPerPage);
        self::assertSame(1, $request->page);
        self::assertNull($request->customSearchInput);
        self::assertSame(['Name', 'CountryCode'], $request->columnListActive);
    }

    public function testFromGlobalsRejectsInvalidValues(): void
    {
        $request = IndexRequest::fromGlobals(
            [],
            [],
            ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/index.php'],
            [
                'SR' => 'evil;DROP TABLE', 'SO' => 'sideways',
                'RowsPerPage' => '999999', 'Page' => '-5',
                'CSField' => 'Password', 'CSMod' => 'Regex',
            ],
            [],
            ['IndexVisited' => 1]
        );

        self::assertSame('Name', $request->sortRequest);
        self::assertSame('Asc', $request->sortOrder);
        self::assertSame(100, $request->rowsPerPage);
        self::assertSame(1, $request->page);
        self::assertSame('Fingerprint', $request->customSearchField);
        self::assertSame('Equals', $request->customSearchModifier);
    }

    public function testFromGlobalsTruncatesOversizedSearchInput(): void
    {
        $request = IndexRequest::fromGlobals(
            [],
            [],
            ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/index.php'],
            ['CSInput' => str_repeat('a', 300)],
            [],
            ['IndexVisited' => 1]
        );

        self::assertSame(128, strlen((string)$request->customSearchInput));
    }

    public function testPaginationEmptyResultSet(): void
    {
        $request = $this->makeRequest(page: 1);
        $pagination = $request->pagination('index.php', 1, 1, 0);

        self::assertSame(0, $pagination['start_result']);
        self::assertSame(0, $pagination['end_result']);
        self::assertSame(0, $pagination['total_results']);
        self::assertNull($pagination['prev']);
        self::assertNull($pagination['next']);
    }

    public function testPaginationRangesAndLinks(): void
    {
        $request = $this->makeRequest(page: 3);
        $pagination = $request->pagination('index.php', 3, 5, 450);

        self::assertSame(201, $pagination['start_result']);
        self::assertSame(300, $pagination['end_result']);
        self::assertStringContainsString('Page=2', (string)$pagination['prev']);
        self::assertStringContainsString('Page=4', (string)$pagination['next']);
        self::assertStringContainsString('RowsPerPage=100', (string)$pagination['next']);
    }

    public function testPaginationLastPageClampsEndResult(): void
    {
        $request = $this->makeRequest(page: 5);
        $pagination = $request->pagination('index.php', 5, 5, 450);

        self::assertSame(401, $pagination['start_result']);
        self::assertSame(450, $pagination['end_result']);
        self::assertNull($pagination['next']);
    }

    public function testPaginationEllipsisGaps(): void
    {
        $request = $this->makeRequest(page: 50);
        $pagination = $request->pagination('index.php', 50, 100, 10000);

        $pages = array_column($pagination['pages'], 'page');
        self::assertContains(null, $pages);
        self::assertContains(1, $pages);
        self::assertContains(50, $pages);
        self::assertContains(100, $pages);
        self::assertNotContains(3, $pages);
        self::assertNotContains(97, $pages);
    }

    public function testHiddenInputsResetPageToOne(): void
    {
        $request = $this->makeRequest(page: 7, filters: ['FExit' => '1']);
        $hidden = $request->toHiddenInputs();

        self::assertSame('1', $hidden['Page']);
        self::assertSame('1', $hidden['FExit']);
        self::assertSame('Name', $hidden['SR']);
    }

    public function testSearchInputExcludedFromQueryParamsWhenNull(): void
    {
        $request = $this->makeRequest(page: 1);
        $query = $request->toBaseQuery();

        self::assertStringNotContainsString('CSInput', $query);
    }

    /** @param array<string, string> $filters */
    private function makeRequest(int $page, array $filters = []): IndexRequest
    {
        $filters += array_fill_keys(IndexRequest::FLAG_FIELDS, 'OFF');
        return new IndexRequest(
            'Name',
            'Asc',
            100,
            $page,
            $filters,
            'Fingerprint',
            'Equals',
            null,
            [],
            []
        );
    }
}
