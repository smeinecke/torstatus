<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once 'init.php';

use TorStatus\ColumnSet\ColumnPreferences;
use TorStatus\ColumnSet\ColumnSetAction;

$pageTitle = 'Column Display Preferences';
$Self = 'column_set.php';

$preferences = ColumnPreferences::fromSession(
    $ColumnList_ACTIVE_DEFAULT,
    $ColumnList_INACTIVE_DEFAULT,
    $_SESSION
);

$action = new ColumnSetAction(null, null, null, null, null, null);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $preferences->applyPost($_POST);
}
$preferences->persist($_SESSION);

render('column_set.html.twig', array_merge(
    [
        'Self' => $Self,
        'ColumnList_ACTIVE' => $preferences->active(),
        'ColumnList_INACTIVE' => $preferences->inactive(),
    ],
    $action->toTemplateContext()
));
