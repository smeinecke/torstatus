<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../init.php';

use TorStatus\Index\ClientContext;
use TorStatus\Index\CountryCodes;
use TorStatus\Index\ExitPolicyMatcher;
use TorStatus\Index\IndexRepository;
use TorStatus\Index\IndexRequest;
use TorStatus\Index\RouterRowBuilder;
use TorStatus\Index\RouterTablePresenter;
use TorStatus\Index\TableNames;
use TorStatus\Index\TorUsageService;

$pageTitle = 'Tor Network Status';
$Self = 'index.php';

$request = IndexRequest::fromGlobals(
    $ColumnList_ACTIVE_DEFAULT,
    $ColumnList_INACTIVE_DEFAULT,
    $_SERVER,
    $_GET,
    $_POST,
    $_SESSION
);
$request->persist($_SESSION);
$CSInput = $request->customSearchInput;

$clientContext = ClientContext::fromServer($_SERVER, $TrustedProxies ?? [], $RealServerIP ?? '');
$tables = new TableNames($ActiveNetworkStatusTable, $ActiveDescriptorTable, $ActiveORAddressesTable);
$repository = new IndexRepository($db, $tables, (int)$OffsetFromGMT);

$routerCount = $repository->countRouters();
$source = $repository->fetchNetworkStatusSource();
$sourceFingerprint = (string)($source['Fingerprint'] ?? '');
$sourceLocation = $sourceFingerprint !== '' ? $repository->fetchNetworkStatusSourceLocation($sourceFingerprint) : [];

$torUsage = (new TorUsageService($repository, new ExitPolicyMatcher()))->evaluate(
    $clientContext->remoteIp,
    $clientContext->serverIp,
    $clientContext->serverPort
);

$descriptorCount = $repository->countDescriptors();
$routerPage = $repository->fetchRouterPage($request);
$request->page = $routerPage->page;

$rowBuilder = new RouterRowBuilder($db, CountryCodes::all(), __DIR__ . '/img/flags');
$routers = $rowBuilder->build($routerPage->result, $request->columnListActive);
$tablePresenter = new RouterTablePresenter();
$baseUrl = $request->toBaseUrl($Self);
$routerPage->result->free();

$currentResultSet = $routerPage->totalResults;
$aggregateStats = $repository->fetchAggregateStats();
$statsRows = $repository->buildStatsRows($aggregateStats, $routerCount, $currentResultSet);

$context = array_merge(
    $request->toTemplateContext(),
    $torUsage,
    [
        'pageTitle' => $pageTitle,
        'routers' => $routers,
        'name_header' => $tablePresenter->nameHeader($request, $baseUrl),
        'table_headers' => $tablePresenter->headers($request, $baseUrl),
        'query_hidden_inputs' => $request->toHiddenInputs(),
        'rows_per_page_options' => $request->rowsPerPageOptions(),
        'pagination' => $request->pagination($Self, $routerPage->page, $routerPage->totalPages),
        'sort_options' => IndexRequest::sortOptions(),
        'filter_options' => IndexRequest::filterOptions(),
        'search_options' => IndexRequest::searchOptions(),
        'search_mod_options' => IndexRequest::searchModifierOptions(),
        'page' => $routerPage->page,
        'total_pages' => $routerPage->totalPages,
        'total_results' => $routerPage->totalResults,
        'Self' => $Self,
        'base_url' => $baseUrl,
        'base_q' => $request->toBaseQuery(),
        'RemoteIP' => $clientContext->remoteIp,
        'stats_rows' => $statsRows,
        'SourceFingerprint' => $sourceFingerprint,
        'SourceName' => (string)($source['Name'] ?? ''),
        'SourceCountryCode' => (string)($sourceLocation['CountryCode'] ?? ''),
        'SourceContact' => $source['Contact'] ?? null,
        'SourcePlatform' => (string)($source['Platform'] ?? ''),
        'SourceIP' => (string)($source['IP'] ?? ''),
        'SourceHostname' => (string)($sourceLocation['Hostname'] ?? ''),
        'SourceORPort' => (string)($source['ORPort'] ?? ''),
        'SourceDirPort' => (string)($source['DirPort'] ?? ''),
        'SourceLastDescriptorPublished' => (string)($source['LastDescriptorPublished'] ?? ''),
        'SourceOnionKey' => (string)($source['OnionKey'] ?? ''),
        'SourceSigningKey' => (string)($source['SigningKey'] ?? ''),
        'SourceDescriptorSignature' => (string)($source['DescriptorSignature'] ?? ''),
        'SourceFingerprint_formatted' => chunk_split(strtoupper($sourceFingerprint), 4, ' '),
        'LastUpdate' => $LastUpdate,
        'LocalTimeZone' => $LocalTimeZone,
        'LastUpdateElapsed' => $LastUpdateElapsed,
        'RouterCount' => $routerCount,
        'DescriptorCount' => $descriptorCount,
        'page_generation_time' => round((microtime(true) - $TimeStart), 4),
    ]
);

$renderer->render('index.html.twig', $context);

$mysqli->close();
$_SESSION['IndexVisited'] = 1;
