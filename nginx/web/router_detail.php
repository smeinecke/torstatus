<?php

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once('init.php');

$Name = null;
$IP = null;
$Hostname = null;
$ORPort = null;
$DirPort = null;
$Fingerprint = null;
$Platform = null;
$LastDescriptorPublished = null;
$Uptime = null;
$Bandwidth_MAX = null;
$Bandwidth_BURST = null;
$Bandwidth_OBSERVED = null;
$OnionKey = null;
$SigningKey = null;
$Contact = null;
$ExitPolicy_DATA_ARRAY = null;
$Family_DATA_ARRAY = null;
$FAuthority = null;
$FBadDirectory = null;
$FBadExit = null;
$FExit = null;
$FFast = null;
$FGuard = null;
$FHibernating = null;
$FNamed = null;
$FStable = null;
$FRunning = null;
$FValid = null;
$FV2Dir = null;
$CountryCode = null;

// Read in submitted variables
if (isset($_GET["FP"]))
{
$Fingerprint = $_GET["FP"];
}
else {
http_response_code(400);
echo('Parameter FP missing');
die();
}

// Perform variable scrubbing
$Fingerprint = strip_tags($Fingerprint);
if (!preg_match('/^[a-fA-F0-9]{40}$/', $Fingerprint))
{
http_response_code(400);
echo('Invalid fingerprint');
die();
}

// Populate variables from database
$query = "select $ActiveNetworkStatusTable.Name, $ActiveDescriptorTable.LastDescriptorPublished, $ActiveNetworkStatusTable.IP, $ActiveNetworkStatusTable.Hostname, $ActiveNetworkStatusTable.ORPort, $ActiveNetworkStatusTable.DirPort, $ActiveDescriptorTable.Platform, $ActiveDescriptorTable.Contact, CAST(UNIX_TIMESTAMP() AS SIGNED) - CAST(UNIX_TIMESTAMP($ActiveDescriptorTable.LastDescriptorPublished) AS SIGNED) + $OffsetFromGMT + CAST($ActiveDescriptorTable.Uptime AS SIGNED) as Uptime, $ActiveDescriptorTable.BandwidthMAX, $ActiveDescriptorTable.BandwidthBURST, $ActiveDescriptorTable.BandwidthOBSERVED, $ActiveDescriptorTable.OnionKey, $ActiveDescriptorTable.SigningKey, $ActiveDescriptorTable.ExitPolicySERDATA, $ActiveDescriptorTable.FamilySERDATA, $ActiveNetworkStatusTable.CountryCode, $ActiveDescriptorTable.Hibernating, $ActiveNetworkStatusTable.FAuthority, $ActiveNetworkStatusTable.FBadDirectory, $ActiveNetworkStatusTable.FBadExit, $ActiveNetworkStatusTable.FExit, $ActiveNetworkStatusTable.FFast, $ActiveNetworkStatusTable.FGuard, $ActiveNetworkStatusTable.FNamed, $ActiveNetworkStatusTable.FStable, $ActiveNetworkStatusTable.FRunning, $ActiveNetworkStatusTable.FValid, $ActiveNetworkStatusTable.FV2Dir from $ActiveNetworkStatusTable inner join $ActiveDescriptorTable on $ActiveNetworkStatusTable.Fingerprint = $ActiveDescriptorTable.Fingerprint where $ActiveNetworkStatusTable.Fingerprint = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $Fingerprint);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if(!$record) {
http_response_code(404);
echo("Unknown fingerprint");
die();
}

$Name = $record['Name'];
$LastDescriptorPublished = $record['LastDescriptorPublished'];
$IP = $record['IP'];
$Hostname = $record['Hostname'];
$ORPort = $record['ORPort'];
$DirPort = $record['DirPort'];
$Platform = $record['Platform'];
$Contact = $record['Contact'];
$Uptime = $record['Uptime'];
$Bandwidth_MAX = $record['BandwidthMAX'];
$Bandwidth_BURST = $record['BandwidthBURST'];
$Bandwidth_OBSERVED = $record['BandwidthOBSERVED'];
$OnionKey = $record['OnionKey'];
$SigningKey = $record['SigningKey'];
$ExitPolicy_DATA_ARRAY = unserialize($record['ExitPolicySERDATA'], ['allowed_classes' => false]);
$Family_DATA_ARRAY = unserialize($record['FamilySERDATA'], ['allowed_classes' => false]);
$CountryCode = $record['CountryCode'];
$FAuthority = $record['FAuthority'];
$FBadDirectory = $record['FBadDirectory'];
$FBadExit = $record['FBadExit'];
$FExit = $record['FExit'];
$FFast = $record['FFast'];
$FGuard = $record['FGuard'];
$FHibernating = $record['Hibernating'];
$FNamed = $record['FNamed'];
$FStable = $record['FStable'];
$FRunning = $record['FRunning'];
$FValid = $record['FValid'];
$FV2Dir = $record['FV2Dir'];

$noindex = true;
$pageTitle = "Router Detail";

// Handle no descriptor available situation
if ($Name == null)
{
render('router_detail.html.twig', [
'error' => 'No Descriptor Available',
]);
$mysqli->close();
exit;
}

$context = [
'Name' => $Name,
'Fingerprint' => $Fingerprint,
'Fingerprint_formatted' => chunk_split(strtoupper($Fingerprint), 4, " "),
'IP' => $IP,
'Hostname' => $Hostname,
'ORPort' => $ORPort,
'DirPort' => $DirPort,
'Platform' => $Platform,
'Contact' => $Contact,
'LastDescriptorPublished' => $LastDescriptorPublished,
'Uptime' => $Uptime,
'uptime_days' => floor($Uptime / 86400),
'uptime_hours' => floor(($Uptime % 86400) / 3600),
'uptime_minutes' => floor(($Uptime % 3600) / 60),
'uptime_seconds' => $Uptime % 60,
'Bandwidth_MAX' => $Bandwidth_MAX,
'Bandwidth_BURST' => $Bandwidth_BURST,
'Bandwidth_OBSERVED' => $Bandwidth_OBSERVED,
'OnionKey' => $OnionKey,
'SigningKey' => $SigningKey,
'ExitPolicy_DATA_ARRAY' => $ExitPolicy_DATA_ARRAY ?? [],
'Family_DATA_ARRAY' => $Family_DATA_ARRAY ?? [],
'CountryCode' => $CountryCode,
'FAuthority' => $FAuthority,
'FBadDirectory' => $FBadDirectory,
'FBadExit' => $FBadExit,
'FExit' => $FExit,
'FFast' => $FFast,
'FGuard' => $FGuard,
'FHibernating' => $FHibernating,
'FNamed' => $FNamed,
'FStable' => $FStable,
'FRunning' => $FRunning,
'FValid' => $FValid,
'FV2Dir' => $FV2Dir,
];

render('router_detail.html.twig', $context);

// Close connection
$mysqli->close();
