<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once 'init.php';

use TorStatus\ExitQuery\TorExitQueryRequest;
use TorStatus\ExitQuery\TorExitQueryService;
use TorStatus\Index\ExitPolicyMatcher;
use TorStatus\Index\IndexRepository;
use TorStatus\Index\TableNames;

$pageTitle = 'Tor Exit Query';
$Self = 'tor_exit_query.php';

$repository = new IndexRepository(
    $db,
    new TableNames($ActiveNetworkStatusTable, $ActiveDescriptorTable, $ActiveORAddressesTable),
    (int)$OffsetFromGMT
);
$request = TorExitQueryRequest::fromGlobals($_SERVER, $_POST);
$context = (new TorExitQueryService($repository, new ExitPolicyMatcher()))->evaluate($request);
$context['Self'] = $Self;

render('tor_exit_query.html.twig', $context);

$mysqli->close();
