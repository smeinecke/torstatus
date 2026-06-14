<?php

function die_503($text) {
	error_log("HTTP 503 returned to client; reason: $text");
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	die();
}

function die_400() {
	header('HTTP/1.1 400 Bad Request');
	header('Status: 400 Bad Request');
	die();
}

function db_query_single_row($query, $cache_expiration = -1, array $params = []) {
	global $mysqli, $memcached;

	$cache_key = null;
	if($cache_expiration > -1) {
		$cache_key = "torstatus_query_" . sha1($query . "\0" . serialize($params));
		$cache_raw = $memcached->get($cache_key);
		if(is_string($cache_raw) && $cache_raw !== '') {
			$record = unserialize($cache_raw, ['allowed_classes' => false]);
			if(is_array($record)) {
				return $record;
			}
		}
	}

	$stmt = $mysqli->prepare($query);
	if(!$stmt) {
		die_503('Query prepare failed: ' . $mysqli->error);
	}

	if($params) {
		$types = '';
		$values = [];
		foreach($params as $param) {
			if(is_int($param) || is_bool($param)) {
				$types .= 'i';
			}
			elseif(is_float($param)) {
				$types .= 'd';
			}
			else {
				$types .= 's';
			}
			$values[] = $param;
		}
		$refs = [];
		foreach($values as $key => &$value) {
			$refs[$key] =& $value;
		}
		$stmt->bind_param($types, ...$refs);
	}

	if(!$stmt->execute()) {
		$error = $stmt->error ?: $mysqli->error;
		$stmt->close();
		die_503('Query failed: ' . $error);
	}

	$result = $stmt->get_result();
	if(!$result) {
		$stmt->close();
		die_503('Query failed: no result set returned');
	}

	$record = $result->fetch_assoc();
	$result->free();
	$stmt->close();

	if(!is_array($record)) {
		$record = [];
	}
	if($cache_key !== null) {
		$memcached->set($cache_key, serialize($record), $cache_expiration);
	}

	return $record;
}

function fetch_mirrors() {
	global $mirrorList;

	// Retrieve the mirror list from the database
	$query = "SELECT mirrors FROM `Mirrors` WHERE id=1";
	$mirrorListRow = db_query_single_row($query, 86400);
	$mirrorList = $mirrorListRow['mirrors'];
}

// Start new session
@session_start() or die_400();


$memcached = new Memcached();
$memcached->addServer($memcached_host, 11211);

// Get script start time
$TimeStart = microtime(true);

// Connect to database, select schema
mysqli_report(MYSQLI_REPORT_STRICT);
try {
	$mysqli = new mysqli($SQL_Server, $SQL_User, $SQL_Pass, $SQL_Catalog);
	if($mysqli->connect_error) {
		die_503('Could not connect to: ' . $mysqli->connect_error);
	}
}
catch (Exception $e) {
	die_503('Could not connect to: ' . $e->getMessage());
}
mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX);

// Get last update and active table information from database
$query = "select LastUpdate, LastUpdateElapsed, ActiveNetworkStatusTable, ActiveDescriptorTable, ActiveORAddressesTable from Status";
$record = db_query_single_row($query, 60);

$LastUpdate = $record['LastUpdate'];
$LastUpdateElapsed = $record['LastUpdateElapsed'];
$ActiveNetworkStatusTable = $record['ActiveNetworkStatusTable'];
$ActiveDescriptorTable = $record['ActiveDescriptorTable'];
$ActiveORAddressesTable = $record['ActiveORAddressesTable'];

$timestamp = time();
$year = date('Y', $timestamp);
$month = date('n', $timestamp);
$day = date('j', $timestamp);
$hour = date('G', $timestamp);
$minute = date('i', $timestamp);
$second = date('s', $timestamp);
$Host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$onion_service = preg_match('/^[0-9a-z]*\.onion$/', $Host);
