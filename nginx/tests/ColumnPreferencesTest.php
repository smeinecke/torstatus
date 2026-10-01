<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\TestCase;
use TorStatus\ColumnSet\ColumnPreferences;

final class ColumnPreferencesTest extends TestCase
{
    public function testFromSessionUsesDefaultsWhenUnvisited(): void
    {
        $prefs = ColumnPreferences::fromSession(['IP', 'Hostname'], ['Platform'], []);
        self::assertSame(['IP', 'Hostname'], $prefs->active());
        self::assertSame(['Platform'], $prefs->inactive());
    }

    public function testFromSessionReadsPersistedLists(): void
    {
        $session = [
            'ColumnSetVisited' => 1,
            'ColumnList_ACTIVE' => ['Fingerprint'],
            'ColumnList_INACTIVE' => ['IP'],
        ];
        $prefs = ColumnPreferences::fromSession(['IP'], ['Fingerprint'], $session);
        self::assertSame(['Fingerprint'], $prefs->active());
        self::assertSame(['IP'], $prefs->inactive());
    }

    public function testConstructorFiltersUnknownColumns(): void
    {
        $prefs = new ColumnPreferences(['IP', 'Hacked', 'IP'], ['Platform']);
        self::assertSame(['IP'], $prefs->active());
    }

    public function testApplyPostAddMovesColumn(): void
    {
        $prefs = new ColumnPreferences(['IP'], ['Hostname']);
        $action = $prefs->applyPost(['CR_INACTIVE' => 'Hostname', 'Add' => '1']);
        self::assertSame('1', $action->add);
        self::assertSame(['IP', 'Hostname'], $prefs->active());
        self::assertSame([], $prefs->inactive());
    }

    public function testApplyPostRemoveMovesColumn(): void
    {
        $prefs = new ColumnPreferences(['IP', 'Hostname'], []);
        $prefs->applyPost(['CR_ACTIVE' => 'IP', 'Remove' => '1']);
        self::assertSame(['Hostname'], $prefs->active());
        self::assertSame(['IP'], $prefs->inactive());
    }

    public function testApplyPostReorders(): void
    {
        $prefs = new ColumnPreferences(['IP', 'Hostname', 'Platform'], []);
        $prefs->applyPost(['CR_ACTIVE' => 'Hostname', 'Up' => '1']);
        self::assertSame(['Hostname', 'IP', 'Platform'], $prefs->active());
        $prefs->applyPost(['CR_ACTIVE' => 'Hostname', 'Down' => '1']);
        self::assertSame(['IP', 'Hostname', 'Platform'], $prefs->active());
    }

    public function testMoveIsBounded(): void
    {
        $prefs = new ColumnPreferences(['IP', 'Hostname'], []);
        $prefs->applyPost(['CR_ACTIVE' => 'IP', 'Up' => '1']);
        self::assertSame(['IP', 'Hostname'], $prefs->active());
        $prefs->applyPost(['CR_ACTIVE' => 'Hostname', 'Down' => '1']);
        self::assertSame(['IP', 'Hostname'], $prefs->active());
    }

    public function testInvalidColumnSelectionIsIgnored(): void
    {
        $prefs = new ColumnPreferences(['IP'], ['Hostname']);
        $prefs->applyPost(['CR_ACTIVE' => 'NotAColumn', 'Remove' => '1']);
        self::assertSame(['IP'], $prefs->active());
        self::assertSame(['Hostname'], $prefs->inactive());
    }

    public function testApplyPostActionContextCarriesSelection(): void
    {
        $prefs = new ColumnPreferences(['IP', 'Hostname', 'Platform'], []);
        $context = $prefs->applyPost(['CR_ACTIVE' => 'Hostname', 'Down' => '1'])->toTemplateContext();
        self::assertSame('Hostname', $context['CR_ACTIVE']);
        self::assertSame('1', $context['CR_Down']);
        self::assertNull($context['CR_Up']);
        self::assertNull($context['CR_Add']);
        self::assertNull($context['CR_Remove']);
        self::assertNull($context['CR_INACTIVE']);
    }

    public function testApplyPostActionContextAfterAdd(): void
    {
        $prefs = new ColumnPreferences(['IP'], ['Hostname']);
        $context = $prefs->applyPost(['CR_INACTIVE' => 'Hostname', 'Add' => '1'])->toTemplateContext();
        self::assertSame('Hostname', $context['CR_INACTIVE']);
        self::assertSame('1', $context['CR_Add']);
        self::assertNull($context['CR_ACTIVE']);
    }

    public function testColumnLabelsCoverAllColumns(): void
    {
        $labels = \TorStatus\Index\RouterTablePresenter::columnLabels();
        foreach (ColumnPreferences::COLUMNS as $column) {
            self::assertArrayHasKey($column, $labels);
            self::assertNotSame('', $labels[$column]);
        }
    }

    public function testPersistWritesSessionKeys(): void
    {
        $prefs = new ColumnPreferences(['IP'], ['Hostname']);
        $session = [];
        $prefs->persist($session);
        self::assertSame(['IP'], $session['ColumnList_ACTIVE']);
        self::assertSame(['Hostname'], $session['ColumnList_INACTIVE']);
        self::assertSame(1, $session['ColumnSetVisited']);
    }
}
