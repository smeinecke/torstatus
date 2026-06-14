<?php

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once('init.php');

$Name = null;
$CountryCode = null;
$IP = null;
$Hostname = null;
$ORPort = null;
$DirPort = null;
$Fingerprint = null;
$Platform = null;
$LastDescriptorPublished = null;
$OnionKey = null;
$SigningKey = null;
$Contact = null;
$DescriptorSignature = null;

$RouterCount = 0;
$DescriptorCount = 0;
$CurrentResultSet = 0;

$RowsPerPage = null;
$Page = null;

$Self = 'index.php';
$RemoteIP = $_SERVER['REMOTE_ADDR'];
// Only honor X-Forwarded-For from trusted proxies (configurable list)
$forwardedFor = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
if ($forwardedFor != '' && in_array($_SERVER['REMOTE_ADDR'], $TrustedProxies ?? [])) {
	$xff = array_map('trim', explode(',', $forwardedFor));
	$xff = array_reverse($xff);
	$clientIP = filter_var($xff[0], FILTER_VALIDATE_IP);
	if ($clientIP !== false) {
		$RemoteIP = $clientIP;
	}
}
$ServerIP = ($forwardedFor != '') ? $RealServerIP : $_SERVER['SERVER_ADDR'];
$ServerPort = $_SERVER['SERVER_PORT'];
$RemoteIPDBCount = null;
$PositiveMatch_IP = 0;
$PositiveMatch_ExitPolicy = null;
$TorNodeName = null;
$TorNodeFP = null;
$TorNodeExitPolicy = null;

$Count = 0;

$ColumnList_ACTIVE = null;
$ColumnList_INACTIVE = null;
$SR = null;
$SO = null;
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
$FHSDir = null;
$CSField = null;
$CSMod = null;
$CSInput = null;

// Create a database of country codes to be used to convert into
// the names of the countries
$country_codes = array (
"nna" => "Unknown Origin",
"a1" => "Anonymous Proxy",
"a2" => "Satellite Provider",
"ac" => "Ascension Island",
"ad" => "Andorra",
"ae" => "United Arab Emirates",
"af" => "Afghanistan",
"ag" => "Antigua And Barbuda",
"ai" => "Anguilla",
"al" => "Albania",
"am" => "Armenia",
"an" => "Netherlands Antilles",
"ao" => "Angola",
"ap" => "Asia/Pacific Region",
"aq" => "Antarctica",
"ar" => "Argentina",
"as" => "American Samoa",
"at" => "Austria",
"au" => "Australia",
"aw" => "Aruba",
"ax" => "Åland Islands",
"az" => "Azerbaijan",
"ba" => "Bosnia And Herzegowina",
"bb" => "Barbados",
"bd" => "Bangladesh",
"be" => "Belgium",
"bf" => "Burkina Faso",
"bg" => "Bulgaria",
"bh" => "Bahrain",
"bi" => "Burundi",
"bj" => "Benin",
"bl" => "Saint Barthélemy",
"bm" => "Bermuda",
"bn" => "Brunei Darussalam",
"bo" => "Bolivia",
"bq" => "Caribbean Netherlands",
"br" => "Brazil",
"bs" => "Bahamas",
"bt" => "Bhutan",
"bv" => "Bouvet Island",
"bw" => "Botswana",
"by" => "Belarus",
"bz" => "Belize",
"ca" => "Canada",
"cc" => "Cocos (Keeling) Islands",
"cd" => "Congo (Democratic Republic)",
"cf" => "Central African Republic",
"cg" => "Congo (Republic)",
"ch" => "Switzerland",
"ci" => "Cote D'Ivoire",
"ck" => "Cook Islands",
"cl" => "Chile",
"cm" => "Cameroon",
"cn" => "China",
"co" => "Colombia",
"cr" => "Costa Rica",
"cu" => "Cuba",
"cv" => "Cape Verde",
"cw" => "Curaçao",
"cx" => "Christmas Island",
"cy" => "Cyprus",
"cz" => "Czech Republic",
"de" => "Germany",
"dj" => "Djibouti",
"dk" => "Denmark",
"dm" => "Dominica",
"do" => "Dominican Republic",
"dz" => "Algeria",
"ec" => "Ecuador",
"ee" => "Estonia",
"eg" => "Egypt",
"eh" => "Western Sahara",
"er" => "Eritrea",
"es" => "Spain",
"et" => "Ethiopia",
"eu" => "European Union",
"fi" => "Finland",
"fj" => "Fiji",
"fk" => "Falkland Islands (Malvinas)",
"fm" => "Micronesia, Federated States Of",
"fo" => "Faroe Islands",
"fr" => "France",
"ga" => "Gabon",
"gb" => "United Kingdom",
"gd" => "Grenada",
"ge" => "Georgia",
"gf" => "French Guiana",
"gg" => "Guernsey",
"gh" => "Ghana",
"gi" => "Gibraltar",
"gl" => "Greenland",
"gm" => "Gambia",
"gn" => "Guinea",
"gp" => "Guadeloupe",
"gq" => "Equatorial Guinea",
"gr" => "Greece",
"gs" => "South Georgia And The South Sandwich Islands",
"gt" => "Guatemala",
"gu" => "Guam",
"gw" => "Guinea-Bissau",
"gy" => "Guyana",
"hk" => "Hong Kong",
"hm" => "Heard And Mc Donald Islands",
"hn" => "Honduras",
"hr" => "Croatia (local name: Hrvatska)",
"ht" => "Haiti",
"hu" => "Hungary",
"id" => "Indonesia",
"ie" => "Ireland",
"il" => "Israel",
"im" => "Isle of Man",
"in" => "India",
"io" => "British Indian Ocean Territory",
"iq" => "Iraq",
"ir" => "Iran (Islamic Republic Of)",
"is" => "Iceland",
"it" => "Italy",
"je" => "Jersey",
"jm" => "Jamaica",
"jo" => "Jordan",
"jp" => "Japan",
"ke" => "Kenya",
"kg" => "Kyrgyzstan",
"kh" => "Cambodia",
"ki" => "Kiribati",
"km" => "Comoros",
"kn" => "Saint Kitts And Nevis",
"kp" => "Korea, Democratic People's Republic of",
"kr" => "Korea, Republic Of",
"kw" => "Kuwait",
"ky" => "Cayman Islands",
"kz" => "Kazakhstan",
"la" => "Lao People's Democratic Republic",
"lb" => "Lebanon",
"lc" => "Saint Lucia",
"li" => "Liechtenstein",
"lk" => "Sri Lanka",
"lr" => "Liberia",
"ls" => "Lesotho",
"lt" => "Lithuania",
"lu" => "Luxembourg",
"lv" => "Latvia",
"ly" => "Libyan Arab Jamahiriya",
"ma" => "Morocco",
"mc" => "Monaco",
"md" => "Moldova, Republic Of",
"me" => "Montenegro",
"mf" => "Saint Martin",
"mg" => "Madagascar",
"mh" => "Marshall Islands",
"mk" => "Macedonia, The Former Yugoslav Republic Of",
"ml" => "Mali",
"mm" => "Myanmar",
"mn" => "Mongolia",
"mo" => "Macau",
"mp" => "Northern Mariana Islands",
"mq" => "Martinique",
"mr" => "Mauritania",
"ms" => "Montserrat",
"mt" => "Malta",
"mu" => "Mauritius",
"mv" => "Maldives",
"mw" => "Malawi",
"mx" => "Mexico",
"my" => "Malaysia",
"mz" => "Mozambique",
"na" => "Namibia",
"nc" => "New Caledonia",
"ne" => "Niger",
"nf" => "Norfolk Island",
"ng" => "Nigeria",
"ni" => "Nicaragua",
"nl" => "Netherlands",
"no" => "Norway",
"np" => "Nepal",
"nr" => "Nauru",
"nu" => "Niue",
"nz" => "New Zealand",
"om" => "Oman",
"pa" => "Panama",
"pe" => "Peru",
"pf" => "French Polynesia",
"pg" => "Papua New Guinea",
"ph" => "Philippines",
"pk" => "Pakistan",
"pl" => "Poland",
"pm" => "St. Pierre And Miquelon",
"pn" => "Pitcairn",
"pr" => "Puerto Rico",
"ps" => "State of Palestine",
"pt" => "Portugal",
"pw" => "Palau",
"py" => "Paraguay",
"qa" => "Qatar",
"re" => "Reunion",
"ro" => "Romania",
"rs" => "Serbia",
"ru" => "Russian Federation",
"rw" => "Rwanda",
"sa" => "Saudi Arabia",
"sb" => "Solomon Islands",
"sc" => "Seychelles",
"sd" => "Sudan",
"se" => "Sweden",
"sg" => "Singapore",
"sh" => "St. Helena",
"si" => "Slovenia",
"sj" => "Svalbard And Jan Mayen Islands",
"sk" => "Slovakia (Slovak Republic)",
"sl" => "Sierra Leone",
"sm" => "San Marino",
"sn" => "Senegal",
"so" => "Somalia",
"sr" => "Suriname",
"ss" => "South Sudan",
"st" => "Sao Tome And Principe",
"su" => "Soviet Union",
"sv" => "El Salvador",
"sx" => "Sint Maarten",
"sy" => "Syrian Arab Republic",
"sz" => "Swaziland",
"tc" => "Turks And Caicos Islands",
"td" => "Chad",
"tf" => "French Southern Territories",
"tg" => "Togo",
"th" => "Thailand",
"tj" => "Tajikistan",
"tk" => "Tokelau",
"tl" => "East Timor",
"tm" => "Turkmenistan",
"tn" => "Tunisia",
"to" => "Tonga",
"tp" => "East Timor",
"tr" => "Turkey",
"tt" => "Trinidad And Tobago",
"tv" => "Tuvalu",
"tw" => "Taiwan, Province Of China",
"tz" => "Tanzania, United Republic Of",
"ua" => "Ukraine",
"ug" => "Uganda",
"uk" => "United Kingdom",
"um" => "United States Minor Outlying Islands",
"us" => "United States",
"uy" => "Uruguay",
"uz" => "Uzbekistan",
"va" => "Vatican City State (Holy See)",
"vc" => "Saint Vincent And The Grenadines",
"ve" => "Venezuela",
"vg" => "Virgin Islands (British)",
"vi" => "Virgin Islands (U.S.)",
"vn" => "Viet Nam",
"vu" => "Vanuatu",
"wf" => "Wallis And Futuna Islands",
"ws" => "Samoa",
"ye" => "Yemen",
"yt" => "Mayotte",
"yu" => "Yugoslavia",
"za" => "South Africa",
"zm" => "Zambia",
"zr" => "Zaire",
"zw" => "Zimbabwe",

);

require_once(dirname(__FILE__) . '/iputils/IpUtils.php');

// Function Declarations
function IsIPInSubnet($IP,$Subnet)
{
	// Credit for the parts of the code in this function:
	// This code used in this function was found on the PHP.net website's 'IP2Long' function page.
	// It was posted by 'Ian B' on '24-Dec-2006 04:22'.

	/* always return true if subnet is wildcard */
	if ($Subnet == '*')
	{
		return true;
	}

	/* always return true if ip is an exact match as is */
	if ($Subnet == $IP)
	{
		return true;
	}

	/* always return false if only an ip was provided, and it's not an exact match */
	if (strpos($Subnet, '/') === FALSE)
	{
		return false;
	}

	return IpUtils::checkIp($IP, $Subnet);
}

function build_router_rows($result)
{
	global $ColumnList_ACTIVE, $country_codes, $notified_missing_countries, $notified_missing_flags, $mysqli;

	$rows = [];
	while ($record = $result->fetch_assoc())
	{
		$countrycode = '';
		if (isset($record['CountryCode']))
		{
			$countrycode = strtolower($record['CountryCode']);
		}
		if ($countrycode != '' && !isset($country_codes[$countrycode]))
		{
			if (!isset($notified_missing_countries))
			{
				$notified_missing_countries = array();
			}
			if (!in_array($countrycode, $notified_missing_countries))
			{
				$parameter = $mysqli->escape_string($countrycode);
				$mysqli->query("INSERT INTO missing_countries (country_code) VALUES ('$parameter') ON DUPLICATE KEY UPDATE country_code = country_code");
				$notified_missing_countries[] = $countrycode;
			}
		}
		if ($countrycode != '' && !file_exists("img/flags/$countrycode.gif"))
		{
			if (!isset($notified_missing_flags))
			{
				$notified_missing_flags = array();
			}
			if (!in_array($countrycode, $notified_missing_flags))
			{
				$parameter = $mysqli->escape_string($countrycode);
				$mysqli->query("INSERT INTO missing_flags (country_code) VALUES ('$parameter') ON DUPLICATE KEY UPDATE country_code = country_code");
				$notified_missing_flags[] = $countrycode;
			}
		}
		if ($countrycode == '' || !isset($country_codes[strtolower($record['CountryCode'])]))
		{
			$countrycode = 'nna';
			$record['CountryCode'] = 'NNA';
		}

		// Row class
		if (isset($record['BadExit']) && $record['BadExit'])
		{		$row_class = 'B';		}
		else if (isset($record['Running']) && isset($record['Hibernating']) && $record['Running'] == 0 && $record['Hibernating'] == 0)
		{		$row_class = 'd';		}
		else if (isset($record['Running']) && isset($record['Hibernating']) && $record['Running'] == 0 && $record['Hibernating'] == 1)
		{		$row_class = 'R';		}
		else
		{		$row_class = 'r';		}

		// Name cell class
		$name_class = (isset($record['Named']) && $record['Named'] == 1) ? 'TRR' : 'TRr';

		// Build column data
		$columns = [];
		foreach ($ColumnList_ACTIVE as $value)
		{			switch (true)
			{			case ($value == 'Hostname'):
					{					$col = ['type' => 'hostname', 'value' => $record[$value] ?? '', 'ip' => $record['IP'] ?? null];
						$flags = [];
						if (isset($record['Fast']) && $record['Fast'] == 1) $flags[] = 'Fast';
						if (isset($record['Valid']) && $record['Valid'] == 0) $flags[] = 'Disputed';
						if (isset($record['Exit']) && $record['Exit'] == 1) $flags[] = 'Exit';
						if (isset($record['V2Dir']) && $record['V2Dir'] == 1) $flags[] = 'Dir';
						if (isset($record['HSDir']) && $record['HSDir'] == 1) $flags[] = 'HSDir';
						if (isset($record['Guard']) && $record['Guard'] == 1) $flags[] = 'Guard';
						if (isset($record['Stable']) && $record['Stable'] == 1) $flags[] = 'Stable';
						if (isset($record['Authority']) && $record['Authority'] == 1) $flags[] = 'Authority';
						$col['flags'] = $flags;
						if (isset($record['Platform']))
						{							$image = 'NotAvailable';
							if (strpos($record['Platform'], 'Linux') !== false || strpos($record['Platform'], 'linux') !== false) $image = 'Linux';
							if (strpos($record['Platform'], 'Windows XP') !== false) $image = 'WindowsXP';
							else if (strpos($record['Platform'], 'Windows') !== false && strpos($record['Platform'], 'server') !== false) $image = 'WindowsServer';
							else if (strpos($record['Platform'], 'Windows') !== false) $image = 'WindowsOther';
							if (strpos($record['Platform'], 'Darwin') !== false) $image = 'Darwin';
							if (strpos($record['Platform'], 'DragonFly') !== false) $image = 'DragonFly';
							if (strpos($record['Platform'], 'FreeBSD') !== false) $image = 'FreeBSD';
							if (strpos($record['Platform'], 'NetBSD') !== false) $image = 'NetBSD';
							if (strpos($record['Platform'], 'IRIX') !== false) $image = 'IRIX64';
							if (strpos($record['Platform'], 'Cygwin') !== false) $image = 'Cygwin';
							if (strpos($record['Platform'], 'SunOS') !== false) $image = 'SunOS';
							if (strpos($record['Platform'], 'OpenBSD') !== false) $image = 'OpenBSD';
							$col['platform'] = $image;
							$col['platform_title'] = $record['Platform'];
						}
						$columns[] = $col;
						break;
					}
					case ($value == 'Bandwidth'):
					{					$bandwidth = $record[$value] ?? 0;
						if ($bandwidth <= 1000) { $bg = 'bwr'; $fg = '1'; }
						else if ($bandwidth <= 2000) { $bg = 'bwr1'; $fg = '2'; }
						else if ($bandwidth <= 3000) { $bg = 'bwr2'; $fg = '3'; }
						else if ($bandwidth <= 4000) { $bg = 'bwr3'; $fg = '4'; }
						else if ($bandwidth <= 5000) { $bg = 'bwr4'; $fg = '5'; }
						else if ($bandwidth <= 6000) { $bg = 'bwr5'; $fg = '6'; }
						else if ($bandwidth <= 10000) { $bandwidth = floor(($bandwidth-6000)/4); $bg = 'bwr6'; $fg = '7'; }
						else { $bandwidth = min(1000, ($bandwidth-9900)/90); $bg = 'bwr7'; $fg = '8'; }
						$bandwidthtop = 1000/85;
						if (intval($bandwidth) % 1000 == 0 && $bandwidth != 0) $bandwidth = 999;
						$bar = floor((intval($bandwidth) % 1000) / $bandwidthtop);
						if ($bar > 85) $bar = 85;
						if ($bar == 0) $bar = 1;
						$columns[] = ['type' => 'bandwidth', 'value' => $record[$value] ?? 0, 'bg' => $bg, 'fg' => $fg, 'bar' => $bar];
						break;
					}
					case (in_array($value, ['Fingerprint', 'LastDescriptorPublished', 'Contact'])):
					{					$columns[] = ['type' => 'text', 'value' => $record[$value] ?? '', 'class' => 'TDS'];
						break;
					}
					case (in_array($value, ['BadDir', 'BadExit'])):
					{					$columns[] = ['type' => 'flag', 'value' => $record[$value] ?? 0];
						break;
					}
					case ($value == 'Uptime'):
					{					$val = $record[$value] ?? -1;
						$cls = ($val >= 5*24) ? 'TDcb' : 'TDc';
						$down = (!isset($record['Running']) || $record['Running'] == 0) && (!isset($record['Hibernating']) || $record['Hibernating'] == 0);
						if ($val > -1)
						{							$days = floor($val/24);
							$hours = $val%24;
							$columns[] = ['type' => 'uptime', 'days' => $days, 'hours' => $hours, 'class' => $cls, 'down' => $down];
						}
						else
						{							$columns[] = ['type' => 'text', 'value' => 'N/A', 'class' => 'TDc', 'down' => true];
						}
						break;
					}
					case ($value == 'ORPort' || $value == 'DirPort'):
					{					$val = $record[$value] ?? 0;
						$columns[] = ['type' => 'port', 'value' => $val, 'class' => 'TDc'];
						break;
					}
					default:
					{					$columns[] = ['type' => 'text', 'value' => $record[$value] ?? '', 'class' => 'TDS'];
						break;
					}
			}
		}

		$rows[] = [
			'row_class' => $row_class,
			'name_class' => $name_class,
			'country_code' => $countrycode,
			'country_name' => $country_codes[strtolower($record['CountryCode'])],
			'Name' => $record['Name'] ?? '',
			'Fingerprint' => $record['Fingerprint'] ?? '',
			'columns' => $columns,
		];
	}
	return $rows;
}

// Read SortRequest (SR) and SortOrder (SO) variables -- These come from POST, GET, or SESSION

// POST
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_POST["SR"]))
	{
		$SR = $_POST["SR"];
	}
	if (isset($_POST["SO"]))
	{
		$SO = $_POST["SO"];
	}
}

// GET
else if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET["SR"]) && isset($_GET["SO"]))
{
	$SR = $_GET["SR"];
	$SO = $_GET["SO"];
}

// SESSION
else
{
	if (isset($_SESSION["SR"]))
	{
		$SR = $_SESSION['SR'];
	}
	if (isset($_SESSION["SO"]))
	{
		$SO = $_SESSION['SO'];
	}
}

// Read RowsPerPage and Page variables -- These come from POST, GET, or SESSION

// POST
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_POST["RowsPerPage"]))
	{
		$RowsPerPage = $_POST["RowsPerPage"];
	}
	if (isset($_POST["Page"]))
	{
		$Page = $_POST["Page"];
	}
}

// GET
else if ($_SERVER['REQUEST_METHOD'] == 'GET')
{
	if (isset($_GET["RowsPerPage"]))
	{
		$RowsPerPage = $_GET["RowsPerPage"];
	}
	if (isset($_GET["Page"]))
	{
		$Page = $_GET["Page"];
	}
}

// SESSION
else
{
	if (isset($_SESSION["RowsPerPage"]))
	{
		$RowsPerPage = $_SESSION['RowsPerPage'];
	}
	if (isset($_SESSION["Page"]))
	{
		$Page = $_SESSION['Page'];
	}
}

// VARIABLE SCRUB / DEFAULT VALUES HANDLING
if(
	$SR != "Name"				&&
	$SR != "Fingerprint"			&&
	$SR != "CountryCode"			&&
	$SR != "Bandwidth"			&&
	$SR != "Uptime"			&&
	$SR != "LastDescriptorPublished"	&&
	$SR != "IP"				&&
	$SR != "Hostname"			&&
	$SR != "ORPort"			&&
	$SR != "DirPort"			&&
	$SR != "Platform"			&&
	$SR != "Contact"			&&
	$SR != "FAuthority"			&&
	$SR != "FBadDirectory"		&&
	$SR != "FBadExit"			&&
	$SR != "FExit"			&&
	$SR != "FFast"			&&
	$SR != "FGuard"			&&
	$SR != "Hibernating"			&&
	$SR != "FNamed"			&&
	$SR != "FStable"			&&
	$SR != "FRunning"			&&
	$SR != "FValid"			&&
	$SR != "FV2Dir"			&&
	$SR != "FHSDir")
{
	$SR = "Name";
}

if(
	$SO != "Asc"				&&
	$SO != "Desc")
{
	$SO = "Asc";
}

if(
	$RowsPerPage != "25"			&&
	$RowsPerPage != "50"			&&
	$RowsPerPage != "100")
{
	$RowsPerPage = "25";
}

$Page = filter_var($Page, FILTER_VALIDATE_INT);
if ($Page === false || $Page < 1)
{
	$Page = 1;
}

// Read CustomSearch Field (CSField), CustomSearch Modifier (CSMod), CustomSearch Input (CSInput), and FLAGS variables -- These come from POST or SESSION

// POST
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_POST["FAuthority"]))
	{
		$FAuthority = $_POST["FAuthority"];
	}
	if (isset($_POST["FBadDirectory"]))
	{
		$FBadDirectory = $_POST["FBadDirectory"];
	}
	if (isset($_POST["FBadExit"]))
	{
		$FBadExit = $_POST["FBadExit"];
	}
	if (isset($_POST["FExit"]))
	{
		$FExit = $_POST["FExit"];
	}
	if (isset($_POST["FFast"]))
	{
		$FFast = $_POST["FFast"];
	}
	if (isset($_POST["FGuard"]))
	{
		$FGuard = $_POST["FGuard"];
	}
	if (isset($_POST["FHibernating"]))
	{
		$FHibernating = $_POST["FHibernating"];
	}
	if (isset($_POST["FNamed"]))
	{
		$FNamed = $_POST["FNamed"];
	}
	if (isset($_POST["FStable"]))
	{
		$FStable = $_POST["FStable"];
	}
	if (isset($_POST["FRunning"]))
	{
		$FRunning = $_POST["FRunning"];
	}
	if (isset($_POST["FValid"]))
	{
		$FValid = $_POST["FValid"];
	}
	if (isset($_POST["FV2Dir"]))
	{
		$FV2Dir = $_POST["FV2Dir"];
	}
	if (isset($_POST["FHSDir"]))
	{
		$FHSDir = $_POST["FHSDir"];
	}
	if (isset($_POST["CSField"]))
	{
		$CSField = $_POST["CSField"];
	}
	if (isset($_POST["CSMod"]))
	{
		$CSMod = $_POST["CSMod"];
	}
	if (isset($_POST["CSInput"]))
	{
		$CSInput = $_POST["CSInput"];
	}
}

// GET
else if ($_SERVER['REQUEST_METHOD'] == 'GET')
{
	if (isset($_GET["FAuthority"]))
	{
		$FAuthority = $_GET["FAuthority"];
	}
	if (isset($_GET["FBadDirectory"]))
	{
		$FBadDirectory = $_GET["FBadDirectory"];
	}
	if (isset($_GET["FBadExit"]))
	{
		$FBadExit = $_GET["FBadExit"];
	}
	if (isset($_GET["FExit"]))
	{
		$FExit = $_GET["FExit"];
	}
	if (isset($_GET["FFast"]))
	{
		$FFast = $_GET["FFast"];
	}
	if (isset($_GET["FGuard"]))
	{
		$FGuard = $_GET["FGuard"];
	}
	if (isset($_GET["FHibernating"]))
	{
		$FHibernating = $_GET["FHibernating"];
	}
	if (isset($_GET["FNamed"]))
	{
		$FNamed = $_GET["FNamed"];
	}
	if (isset($_GET["FStable"]))
	{
		$FStable = $_GET["FStable"];
	}
	if (isset($_GET["FRunning"]))
	{
		$FRunning = $_GET["FRunning"];
	}
	if (isset($_GET["FValid"]))
	{
		$FValid = $_GET["FValid"];
	}
	if (isset($_GET["FV2Dir"]))
	{
		$FV2Dir = $_GET["FV2Dir"];
	}
	if (isset($_GET["FHSDir"]))
	{
		$FHSDir = $_GET["FHSDir"];
	}
	if (isset($_GET["CSField"]))
	{
		$CSField = $_GET["CSField"];
	}
	if (isset($_GET["CSMod"]))
	{
		$CSMod = $_GET["CSMod"];
	}
	if (isset($_GET["CSInput"]))
	{
		$CSInput = $_GET["CSInput"];
	}
}

// SESSION
else
{
	if (isset($_SESSION["FAuthority"]))
	{
		$FAuthority = $_SESSION["FAuthority"];
	}
	if (isset($_SESSION["FBadDirectory"]))
	{
		$FBadDirectory = $_SESSION["FBadDirectory"];
	}
	if (isset($_SESSION["FBadExit"]))
	{
		$FBadExit = $_SESSION["FBadExit"];
	}
	if (isset($_SESSION["FExit"]))
	{
		$FExit = $_SESSION["FExit"];
	}
	if (isset($_SESSION["FFast"]))
	{
		$FFast = $_SESSION["FFast"];
	}
	if (isset($_SESSION["FGuard"]))
	{
		$FGuard = $_SESSION["FGuard"];
	}
	if (isset($_SESSION["FHibernating"]))
	{
		$FHibernating = $_SESSION["FHibernating"];
	}
	if (isset($_SESSION["FNamed"]))
	{
		$FNamed = $_SESSION["FNamed"];
	}
	if (isset($_SESSION["FStable"]))
	{
		$FStable = $_SESSION["FStable"];
	}
	if (isset($_SESSION["FRunning"]))
	{
		$FRunning = $_SESSION["FRunning"];
	}
	if (isset($_SESSION["FValid"]))
	{
		$FValid = $_SESSION["FValid"];
	}
	if (isset($_SESSION["FV2Dir"]))
	{
		$FV2Dir = $_SESSION["FV2Dir"];
	}
	if (isset($_SESSION["FHSDir"]))
	{
		$FHSDir = $_SESSION["FHSDir"];
	}
	if (isset($_SESSION["CSField"]))
	{
		$CSField = $_SESSION["CSField"];
	}
	if (isset($_SESSION["CSMod"]))
	{
		$CSMod = $_SESSION["CSMod"];
	}
	if (isset($_SESSION["CSInput"]))
	{
		$CSInput = $_SESSION["CSInput"];
	}
}

// Read ColumnList_ACTIVE and ColumnList_INACTIVE variables -- These come from SESSION
	if (isset($_SESSION["ColumnList_ACTIVE"]))
	{
		$ColumnList_ACTIVE = $_SESSION["ColumnList_ACTIVE"];
	}
	if (isset($_SESSION["ColumnList_INACTIVE"]))
	{
		$ColumnList_INACTIVE = $_SESSION["ColumnList_INACTIVE"];
	}

// VARIABLE SCRUB / DEFAULT VALUES HANDLING
if (!(isset($_SESSION['ColumnSetVisited'])) && !(isset($_SESSION['IndexVisited'])))
{
	$ColumnList_ACTIVE = $ColumnList_ACTIVE_DEFAULT;
	$ColumnList_INACTIVE = $ColumnList_INACTIVE_DEFAULT;
}

if($FAuthority != '0' && $FAuthority != '1' && $FAuthority != 'OFF')
{
	$FAuthority = 'OFF';
}

if($FBadDirectory != '0' && $FBadDirectory != '1' && $FBadDirectory != 'OFF')
{
	$FBadDirectory = 'OFF';
}

if($FBadExit != '0' && $FBadExit != '1' && $FBadExit != 'OFF')
{
	$FBadExit = 'OFF';
}

if($FExit != '0' && $FExit != '1' && $FExit != 'OFF')
{
	$FExit = 'OFF';
}

if($FFast != '0' && $FFast != '1' && $FFast != 'OFF')
{
	$FFast = 'OFF';
}

if($FGuard != '0' && $FGuard != '1' && $FGuard != 'OFF')
{
	$FGuard = 'OFF';
}

if($FHibernating != '0' && $FHibernating != '1' && $FHibernating != 'OFF')
{
	$FHibernating = 'OFF';
}

if($FNamed != '0' && $FNamed != '1' && $FNamed != 'OFF')
{
	$FNamed = 'OFF';
}

if($FStable != '0' && $FStable != '1' && $FStable != 'OFF')
{
	$FStable = 'OFF';
}

if($FRunning != '0' && $FRunning != '1' && $FRunning != 'OFF')
{
	$FRunning = 'OFF';
}

if($FValid != '0' && $FValid != '1' && $FValid != 'OFF')
{
	$FValid = 'OFF';
}

if($FV2Dir != '0' && $FV2Dir != '1' && $FV2Dir != 'OFF')
{
	$FV2Dir = 'OFF';
}

if($FHSDir != '0' && $FHSDir != '1' && $FHSDir != 'OFF')
{
	$FHSDir = 'OFF';
}

if(
	$CSField != "Fingerprint"			&&
	$CSField != "Name"				&&
	$CSField != "CountryCode"			&&
	$CSField != "Bandwidth"			&&
	$CSField != "Uptime"				&&
	$CSField != "LastDescriptorPublished"	&&
	$CSField != "IP"				&&
	$CSField != "Hostname"			&&
	$CSField != "ORPort"				&&
	$CSField != "DirPort"			&&
	$CSField != "Platform"			&&
	$CSField != "Contact")
{
	$CSField = "Fingerprint";
}

if(
	$CSMod != "Equals"		&&
	$CSMod != "Contains"		&&
	$CSMod != "LessThan"		&&
	$CSMod != "GreaterThan")
{
	$CSMod = "Equals";
}

if ($CSInput != null)
{
	if (strlen($CSInput) > 128)
	{
		$CSInput = substr($CSInput,0,128);
	}
}

// Register variables in SESSION
if (!isset($_SESSION['ColumnList_ACTIVE']))
{
	$_SESSION['ColumnList_ACTIVE'] = $ColumnList_ACTIVE;
}
else
{
	unset($_SESSION['ColumnList_ACTIVE']);
	$_SESSION['ColumnList_ACTIVE'] = $ColumnList_ACTIVE;
}

if (!isset($_SESSION['ColumnList_INACTIVE']))
{
	$_SESSION['ColumnList_INACTIVE'] = $ColumnList_INACTIVE;
}
else
{
	unset($_SESSION['ColumnList_INACTIVE']);
	$_SESSION['ColumnList_INACTIVE'] = $ColumnList_INACTIVE;
}

if (!isset($_SESSION['SR']))
{
	$_SESSION['SR'] = $SR;
}
else
{
	unset($_SESSION['SR']);
	$_SESSION['SR'] = $SR;
}

if (!isset($_SESSION['SO']))
{
	$_SESSION['SO'] = $SO;
}
else
{
	unset($_SESSION['SO']);
	$_SESSION['SO'] = $SO;
}

if (!isset($_SESSION['RowsPerPage']))
{
	$_SESSION['RowsPerPage'] = $RowsPerPage;
}
else
{
	unset($_SESSION['RowsPerPage']);
	$_SESSION['RowsPerPage'] = $RowsPerPage;
}

if (!isset($_SESSION['Page']))
{
	$_SESSION['Page'] = $Page;
}
else
{
	unset($_SESSION['Page']);
	$_SESSION['Page'] = $Page;
}

if (!isset($_SESSION['FAuthority']))
{
	$_SESSION['FAuthority'] = $FAuthority;
}
else
{
	unset($_SESSION['FAuthority']);
	$_SESSION['FAuthority'] = $FAuthority;
}

if (!isset($_SESSION['FBadDirectory']))
{
	$_SESSION['FBadDirectory'] = $FBadDirectory;
}
else
{
	unset($_SESSION['FBadDirectory']);
	$_SESSION['FBadDirectory'] = $FBadDirectory;
}

if (!isset($_SESSION['FBadExit']))
{
	$_SESSION['FBadExit'] = $FBadExit;
}
else
{
	unset($_SESSION['FBadExit']);
	$_SESSION['FBadExit'] = $FBadExit;
}

if (!isset($_SESSION['FExit']))
{
	$_SESSION['FExit'] = $FExit;
}
else
{
	unset($_SESSION['FExit']);
	$_SESSION['FExit'] = $FExit;
}

if (!isset($_SESSION['FFast']))
{
	$_SESSION['FFast'] = $FFast;
}
else
{
	unset($_SESSION['FFast']);
	$_SESSION['FFast'] = $FFast;
}

if (!isset($_SESSION['FGuard']))
{
	$_SESSION['FGuard'] = $FGuard;
}
else
{
	unset($_SESSION['FGuard']);
	$_SESSION['FGuard'] = $FGuard;
}

if (!isset($_SESSION['FHibernating']))
{
	$_SESSION['FHibernating'] = $FHibernating;
}
else
{
	unset($_SESSION['FHibernating']);
	$_SESSION['FHibernating'] = $FHibernating;
}

if (!isset($_SESSION['FNamed']))
{
	$_SESSION['FNamed'] = $FNamed;
}
else
{
	unset($_SESSION['FNamed']);
	$_SESSION['FNamed'] = $FNamed;
}

if (!isset($_SESSION['FStable']))
{
	$_SESSION['FStable'] = $FStable;
}
else
{
	unset($_SESSION['FStable']);
	$_SESSION['FStable'] = $FStable;
}

if (!isset($_SESSION['FRunning']))
{
	$_SESSION['FRunning'] = $FRunning;
}
else
{
	unset($_SESSION['FRunning']);
	$_SESSION['FRunning'] = $FRunning;
}

if (!isset($_SESSION['FValid']))
{
	$_SESSION['FValid'] = $FValid;
}
else
{
	unset($_SESSION['FValid']);
	$_SESSION['FValid'] = $FValid;
}

if (!isset($_SESSION['FV2Dir']))
{
	$_SESSION['FV2Dir'] = $FV2Dir;
}
else
{
	unset($_SESSION['FV2Dir']);
	$_SESSION['FV2Dir'] = $FV2Dir;
}

if (!isset($_SESSION['FHSDir']))
{
	$_SESSION['FHSDir'] = $FHSDir;
}
else
{
	unset($_SESSION['FHSDir']);
	$_SESSION['FHSDir'] = $FHSDir;
}

if (!isset($_SESSION['CSField']))
{
	$_SESSION['CSField'] = $CSField;
}
else
{
	unset($_SESSION['CSField']);
	$_SESSION['CSField'] = $CSField;
}

if (!isset($_SESSION['CSMod']))
{
	$_SESSION['CSMod'] = $CSMod;
}
else
{
	unset($_SESSION['CSMod']);
	$_SESSION['CSMod'] = $CSMod;
}

if (!isset($_SESSION['CSInput']))
{
	$_SESSION['CSInput'] = $CSInput;
}
else
{
	unset($_SESSION['CSInput']);
	$_SESSION['CSInput'] = $CSInput;
}

// Get total number of routers from database
$query = "select count(*) as Count from $ActiveNetworkStatusTable";
$record = db_query_single_row($query, 1800);

$RouterCount = $record['Count'];

// Get details on Network Status Source router from the database
$query = "select Name, IP, ORPort, DirPort, Fingerprint, Platform, LastDescriptorPublished, OnionKey, SigningKey, Contact, DescriptorSignature from NetworkStatusSource where ID = 1";
$record = db_query_single_row($query, 1800);

$Name = $record ? $record['Name'] : '';
$IP = $record ? $record['IP'] : '';
$ORPort = $record ? $record['ORPort'] : '';
$DirPort = $record ? $record['DirPort'] : '';
$Fingerprint = $record ? $record['Fingerprint'] : '';
$Platform = $record ? $record['Platform'] : '';
$LastDescriptorPublished = $record ? $record['LastDescriptorPublished'] : '';
$OnionKey = $record ? $record['OnionKey'] : '';
$SigningKey = $record ? $record['SigningKey'] : '';
$Contact = $record ? $record['Contact'] : '';
$DescriptorSignature = $record ? $record['DescriptorSignature'] : '';

$RemoteIP_ESC = $mysqli->real_escape_string($RemoteIP);

if ($Fingerprint)
{
	$query = "select Hostname, CountryCode from $ActiveNetworkStatusTable where Fingerprint = '" . $mysqli->real_escape_string($Fingerprint) . "'";
	$record = db_query_single_row($query, 1800);

	$Hostname = $record['Hostname'];
	$CountryCode = $record['CountryCode'];
}

// Determine if client IP exists in database as a Tor server
$query = "select count(*) as Count from $ActiveNetworkStatusTable where IP = '$RemoteIP_ESC' and FExit = 1";
$record = db_query_single_row($query, 1800);

$RemoteIPDBCount = $record['Count'];

if ($RemoteIPDBCount > 0)
{
	$PositiveMatch_IP = 1;
}
else {
	$query = "select count(*) as Count
		from $ActiveORAddressesTable
			join $ActiveDescriptorTable on $ActiveDescriptorTable.ID = $ActiveORAddressesTable.descriptor_id
			join $ActiveNetworkStatusTable on $ActiveNetworkStatusTable.Fingerprint = $ActiveDescriptorTable.Fingerprint
		where address = '$RemoteIP_ESC'
			and $ActiveNetworkStatusTable.FExit = 1";
	$record = db_query_single_row($query, 1800);

	$RemoteIPDBCount = $record['Count'];

	if ($RemoteIPDBCount > 0)
	{
		$PositiveMatch_IP = 1;
	}
}

// Get name, fingerprint, and exit policy of Tor node(s) if match was found, look for match in ExitPolicy
if ($PositiveMatch_IP == 1)
{
	$query = "select $ActiveNetworkStatusTable.Name Name, $ActiveNetworkStatusTable.Fingerprint Fingerprint, $ActiveDescriptorTable.ExitPolicySERDATA ExitPolicySERDATA
		from $ActiveNetworkStatusTable
			inner join $ActiveDescriptorTable on $ActiveNetworkStatusTable.Fingerprint = $ActiveDescriptorTable.Fingerprint
			left join $ActiveORAddressesTable on $ActiveDescriptorTable.ID = $ActiveORAddressesTable.descriptor_id
		where ($ActiveNetworkStatusTable.IP = '$RemoteIP_ESC' or $ActiveORAddressesTable.address = '$RemoteIP_ESC')
		group by Name, Fingerprint, ExitPolicySERDATA";
	$result = $mysqli->query($query);
	if(!$result) {
		die_503('Query failed: ' . $mysqli->error);
	}

	while ($record = $result->fetch_assoc())
	{
		$Count++;

		$TorNodeName[$Count] = $record['Name'];
		$TorNodeFP[$Count] = $record['Fingerprint'];
		$TorNodeExitPolicy = unserialize($record['ExitPolicySERDATA'], ['allowed_classes' => false]);

		foreach($TorNodeExitPolicy as $ExitPolicyLine)
		{
			// Initialize variables
			$Condition = null;
			$NetworkLine = null;
			$Subnet = null;
			$PortLine = null;
			$Port = null;

			// Seperate parts of ExitPolicy line
			list($Condition,$NetworkLine) = explode(' ', rtrim($ExitPolicyLine));
			$matches = array();
			preg_match('/(.*):([^:]*)$/', $NetworkLine, $matches);
			$Subnet = trim($matches[1], '[]');
			$PortLine = $matches[2];
			$Port = explode(',', $PortLine);

			// Find out if IP client used to access this server is a match for the subnet specified on this ExitPolicy line
			if (IsIPInSubnet($ServerIP,$Subnet))
			{
				// Determine if port is also a match
				foreach($Port as $CurrentPortExpression)
				{
					// Handle condition where port is a '*' character (Port always matches)
					if ($CurrentPortExpression == '*')
					{
						if ($Condition == 'accept' || $Condition == 'accept6')
						{
							$PositiveMatch_ExitPolicy[$Count] = 1;
							break 2;
						}
						else if ($Condition == 'reject' || $Condition == 'reject6')
						{
							$PositiveMatch_ExitPolicy[$Count] = 0;
							break 2;
						}
					}

					// $CurrentPortExpression is a range of ports
					if(strpos($CurrentPortExpression, '-') !== FALSE)
					{
						list($LowerPort,$UpperPort) = explode('-', $CurrentPortExpression);

						if (($ServerPort >= $LowerPort && $ServerPort <= $UpperPort) && ($Condition == 'accept'))
						{
							$PositiveMatch_ExitPolicy[$Count] = 1;
							break 2;
						}
						else if (($ServerPort >= $LowerPort && $ServerPort <= $UpperPort) && ($Condition == 'reject'))
						{
							$PositiveMatch_ExitPolicy[$Count] = 0;
							break 2;
						}
						else
						{
							continue;
						}
					}

					// $CurrentPortExpression is a single port number
					else
					{
						if (($ServerPort == $CurrentPortExpression) && ($Condition == 'accept'))
						{
							$PositiveMatch_ExitPolicy[$Count] = 1;
							break 2;
						}
						else if (($ServerPort == $CurrentPortExpression) && ($Condition == 'reject'))
						{
							$PositiveMatch_ExitPolicy[$Count] = 0;
							break 2;
						}
						else
						{
							continue;
						}
					}
				}
			}
			else
			{
				continue;
			}
		}
	}

	$result->free();
}

// Get descriptor count
$query = "select count(*) as Count from $ActiveDescriptorTable";
$record = db_query_single_row($query, 1800);

$DescriptorCount = $record['Count'];

// Prepare and execute master router query
$query = "select $ActiveNetworkStatusTable.Name, $ActiveNetworkStatusTable.Fingerprint";
$query .= ", $ActiveNetworkStatusTable.CountryCode";
$query .= ", floor($ActiveDescriptorTable.BandwidthOBSERVED / 1024) as Bandwidth";
$query .= ", floor(((UNIX_TIMESTAMP() - (UNIX_TIMESTAMP($ActiveDescriptorTable.LastDescriptorPublished) + $OffsetFromGMT)) + CAST($ActiveDescriptorTable.Uptime AS DECIMAL)) / 3600) as Uptime";
$query .= ", $ActiveDescriptorTable.LastDescriptorPublished";
$query .= ", $ActiveNetworkStatusTable.Hostname";
$query .= ", $ActiveNetworkStatusTable.IP";
$query .= ", $ActiveNetworkStatusTable.ORPort";
$query .= ", $ActiveNetworkStatusTable.DirPort";
$query .= ", $ActiveDescriptorTable.Platform";
$query .= ", $ActiveDescriptorTable.Contact";
$query .= ", $ActiveNetworkStatusTable.FAuthority as Authority";
$query .= ", $ActiveNetworkStatusTable.FBadDirectory as BadDir";
$query .= ", $ActiveNetworkStatusTable.FBadExit as BadExit";
$query .= ", $ActiveNetworkStatusTable.FExit as 'Exit'";
$query .= ", $ActiveNetworkStatusTable.FFast as Fast";
$query .= ", $ActiveNetworkStatusTable.FGuard as Guard";
$query .= ", $ActiveDescriptorTable.Hibernating as 'Hibernating'";
$query .= ", $ActiveNetworkStatusTable.FNamed as Named";
$query .= ", $ActiveNetworkStatusTable.FStable as Stable";
$query .= ", $ActiveNetworkStatusTable.FRunning as Running";
$query .= ", $ActiveNetworkStatusTable.FValid as Valid";
$query .= ", $ActiveNetworkStatusTable.FV2Dir as V2Dir";
$query .= ", $ActiveNetworkStatusTable.FHSDir as HSDir";
$query .= ", INET_ATON($ActiveNetworkStatusTable.IP) as NIP from $ActiveNetworkStatusTable inner join $ActiveDescriptorTable on $ActiveNetworkStatusTable.Fingerprint = $ActiveDescriptorTable.Fingerprint";

if ($FAuthority != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FAuthority = $FAuthority";
		}
	else
		{
			$query = $query . " and FAuthority = $FAuthority";
		}
}

if ($FBadDirectory != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FBadDirectory = $FBadDirectory";
		}
	else
		{
			$query = $query . " and FBadDirectory = $FBadDirectory";
		}
}

if ($FBadExit != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FBadExit = $FBadExit";
		}
	else
		{
			$query = $query . " and FBadExit = $FBadExit";
		}
}

if ($FExit != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FExit = $FExit";
		}
	else
		{
			$query = $query . " and FExit = $FExit";
		}
}

if ($FFast != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FFast = $FFast";
		}
	else
		{
			$query = $query . " and FFast = $FFast";
		}
}

if ($FGuard != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FGuard = $FGuard";
		}
	else
		{
			$query = $query . " and FGuard = $FGuard";
		}
}

if ($FHibernating != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where Hibernating = $FHibernating";
		}
	else
		{
			$query = $query . " and Hibernating = $FHibernating";
		}
}

if ($FNamed != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FNamed = $FNamed";
		}
	else
		{
			$query = $query . " and FNamed = $FNamed";
		}
}

if ($FStable != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FStable = $FStable";
		}
	else
		{
			$query = $query . " and FStable = $FStable";
		}
}

if ($FRunning != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FRunning = $FRunning";
		}
	else
		{
			$query = $query . " and FRunning = $FRunning";
		}
}

if ($FValid != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FValid = $FValid";
		}
	else
		{
			$query = $query . " and FValid = $FValid";
		}
}

if ($FV2Dir != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FV2Dir = $FV2Dir";
		}
	else
		{
			$query = $query . " and FV2Dir = $FV2Dir";
		}
}

if ($FHSDir != 'OFF')
{
	if (strpos($query, "where") === false)
		{
			$query = $query . " where FHSDir = $FHSDir";
		}
	else
		{
			$query = $query . " and FHSDir = $FHSDir";
		}
}

if ($CSInput != null)
{
	$CSInput_SAFE = null;
	$QueryPrepend = null;

	if (strpos($query, "where") === false)
	{
		$QueryPrepend = " where ";
	}
	else
	{
		$QueryPrepend = " and ";
	}

	$query .= $QueryPrepend;

	$CSInput_SAFE = $mysqli->escape_string($CSInput);

	if ($CSField == 'Fingerprint')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.Fingerprint = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.Fingerprint like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.Fingerprint < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.Fingerprint > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'Name')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.Name = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.Name like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.Name < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.Name > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'CountryCode')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.CountryCode = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.CountryCode like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.CountryCode < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.CountryCode > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'Bandwidth')
	{
		if(!(is_numeric($CSInput_SAFE)))
		{
			$CSInput = 0;
			$CSInput_SAFE = 0;
		}

		if($CSMod == 'Equals')
		{
			$query .= "floor($ActiveDescriptorTable.BandwidthOBSERVED / 1024) = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "floor($ActiveDescriptorTable.BandwidthOBSERVED / 1024) like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "floor($ActiveDescriptorTable.BandwidthOBSERVED / 1024) < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "floor($ActiveDescriptorTable.BandwidthOBSERVED / 1024) > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'Uptime')
	{
		if(!(is_numeric($CSInput_SAFE)))
		{
			$CSInput = 0;
			$CSInput_SAFE = 0;
		}

		if($CSMod == 'Equals')
		{
			$query .= "floor($ActiveDescriptorTable.Uptime / 3600) = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "floor($ActiveDescriptorTable.Uptime / 3600) like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "floor($ActiveDescriptorTable.Uptime / 3600) < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "floor($ActiveDescriptorTable.Uptime / 3600) > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'LastDescriptorPublished')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.LastDescriptorPublished = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.LastDescriptorPublished like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.LastDescriptorPublished < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.LastDescriptorPublished > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'IP')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.IP = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.IP like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.IP < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.IP > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'Hostname')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.Hostname = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.Hostname like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.Hostname < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.Hostname > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'ORPort')
	{
		if(!(is_numeric($CSInput_SAFE)))
		{
			$CSInput = 0;
			$CSInput_SAFE = 0;
		}

		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.ORPort = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.ORPort like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.ORPort < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.ORPort > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'DirPort')
	{
		if(!(is_numeric($CSInput_SAFE)))
		{
			$CSInput = 0;
			$CSInput_SAFE = 0;
		}

		if($CSMod == 'Equals')
		{
			$query .= "$ActiveNetworkStatusTable.DirPort = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveNetworkStatusTable.DirPort like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveNetworkStatusTable.DirPort < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveNetworkStatusTable.DirPort > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'Platform')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveDescriptorTable.Platform = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveDescriptorTable.Platform like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveDescriptorTable.Platform < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveDescriptorTable.Platform > '$CSInput_SAFE'";
		}
	}
	else if ($CSField == 'Contact')
	{
		if($CSMod == 'Equals')
		{
			$query .= "$ActiveDescriptorTable.Contact = '$CSInput_SAFE'";
		}
		else if($CSMod == 'Contains')
		{
			$query .= "$ActiveDescriptorTable.Contact like '%$CSInput_SAFE%'";
		}
		else if($CSMod == 'LessThan')
		{
			$query .= "$ActiveDescriptorTable.Contact < '$CSInput_SAFE'";
		}
		else if($CSMod == 'GreaterThan')
		{
			$query .= "$ActiveDescriptorTable.Contact > '$CSInput_SAFE'";
		}
	}
}

$query .= " group by $ActiveDescriptorTable.Fingerprint";

if ($SR == 'Name')
{
	$query = $query . " order by " . $SR . " " . $SO;
}
else if ($SR == 'IP')
{
	$query = $query . " order by NIP " . $SO . ", Name Asc";
}
else if (in_array($SR, array('ID', 'Fingerprint', 'Name', 'LastDescriptorPublished', 'IP', 'ORPort', 'DirPort')))
{
	$query = $query . " order by $ActiveNetworkStatusTable.$SR $SO";
}
else
{
	$query = $query . " order by " . $SR . " " . $SO . ", Name Asc";
}

// Count total results for pagination (same WHERE clauses)
// Wrap the grouped query as a subquery so COUNT(*) gives the number of distinct groups
$countQuery = "SELECT COUNT(*) AS Count FROM (" . $query . ") AS countQuery";
$countResult = $mysqli->query($countQuery);
if (!$countResult) {
	die_503('Count query failed: ' . $mysqli->error);
}
$countRecord = $countResult->fetch_assoc();
$TotalResults = $countRecord['Count'];
$countResult->free();

$TotalPages = max(1, ceil($TotalResults / (int)$RowsPerPage));
if ($Page > $TotalPages) {
	$Page = $TotalPages;
}
$Offset = ($Page - 1) * (int)$RowsPerPage;

$query .= " LIMIT " . (int)$RowsPerPage . " OFFSET " . (int)$Offset;

$result = $mysqli->query($query);
if(!$result) {
	die_503('Query failed: ' . $mysqli->error);
}

$routers = build_router_rows($result);
$result->free();
$CurrentResultSet = $TotalResults;

$pageTitle = "Tor Network Status";

// stats block
ob_start();
?>
<table width='40%' cellspacing='0' cellpadding='0' class='displayTable' id='anssTable'>
<tr>
<td class='HRN' colspan='3'>Aggregate Network Statistic Summary | <a href='network_detail.php'>Graphs / Details</a></td>
</tr>

<?php

// Retrieve statistics from database
$query = "select
	(select count(*) from $ActiveNetworkStatusTable) as 'Total',
	(select count(*) from $ActiveNetworkStatusTable where FAuthority = '1') as 'Authority',
	(select count(*) from $ActiveNetworkStatusTable where FBadDirectory = '1') as 'BadDirectory',
	(select count(*) from $ActiveNetworkStatusTable where FBadExit = '1') as 'BadExit',
	(select count(*) from $ActiveNetworkStatusTable where FExit = '1') as 'Exit',
	(select count(*) from $ActiveNetworkStatusTable where FFast = '1') as 'Fast',
	(select count(*) from $ActiveNetworkStatusTable where FGuard = '1') as 'Guard',
	(select count(*) from $ActiveDescriptorTable inner join $ActiveNetworkStatusTable on $ActiveNetworkStatusTable.Fingerprint = $ActiveDescriptorTable.Fingerprint where Hibernating = '1') as 'Hibernating',
	(select count(*) from $ActiveNetworkStatusTable where FNamed = '1') as 'Named',
	(select count(*) from $ActiveNetworkStatusTable where FStable = '1') as 'Stable',
	(select count(*) from $ActiveNetworkStatusTable where FRunning = '1') as 'Running',
	(select count(*) from $ActiveNetworkStatusTable where FValid = '1') as 'Valid',
	(select count(*) from $ActiveNetworkStatusTable where FV2Dir = '1') as 'V2Dir',
	(select count(*) from $ActiveNetworkStatusTable where FHSDir = '1') as 'HSDir',
	(select count(*) from $ActiveNetworkStatusTable where DirPort > 0) as 'DirMirror'";

$record = db_query_single_row($query, 1800);

// Display total number of routers
if ($RouterCount != 0)
{
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of Routers:</b></td>\n";
echo "<td class='TRS'>$RouterCount</td>\n";
echo "<td class='TRS'>" . round((($RouterCount / $RouterCount) * 100),2) . "%</td>\n";	echo "</tr>\n";

// Display number of routers in current result set
echo "<tr>\n";
echo "<td class='TRAR'><b>Routers in Current Query Result Set:</b></td>\n";
echo "<td class='TRS'>$CurrentResultSet</td>\n";
echo "<td class='TRS'>" . round((($CurrentResultSet / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are Authority servers
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Authority' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Authority'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Authority'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are BadDirectory servers
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Bad Directory' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['BadDirectory'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['BadDirectory'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are bad exits
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Bad Exit' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['BadExit'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['BadExit'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are exits
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Exit' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Exit'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Exit'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are fast
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Fast' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Fast'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Fast'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are guards
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Guard' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Guard'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Guard'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers hibernating
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Hibernating' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Hibernating'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Hibernating'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are named
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Named' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Named'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Named'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are stable
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Stable' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Stable'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Stable'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are running
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Running' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Running'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Running'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are valid
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Valid' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['Valid'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['Valid'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are V2Dir ready
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'V2Dir' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['V2Dir'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['V2Dir'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers which are HSDir ready
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'HSDir' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['HSDir'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['HSDir'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";

// Display total number of routers mirroring directory
echo "<tr>\n";
echo "<td class='TRAR'><b>Total Number of 'Directory Mirror' Routers:</b></td>\n";
echo "<td class='TRS'>" . $record['DirMirror'] . "</td>\n";
echo "<td class='TRS'>" . round((($record['DirMirror'] / $RouterCount) * 100),2) . "%</td>\n";
echo "</tr>\n";
}
?>
</table>

<?php
$stats_block = ob_get_clean();

// nsos block
ob_start();
?>
<table width='100%' cellspacing='0' cellpadding='0' class='displayTable' id='nsosTable'>
<tr>
<td class='HRN'>Network Status Opinion Source</td>
</tr>
<tr>
<td class='TRSB'>
<?php

if($Fingerprint)
{
	echo "<b>Nickname:</b><br/><a class='plainbox' href='router_detail.php?FP=" . htmlspecialchars($Fingerprint, ENT_QUOTES) . "'>" . htmlspecialchars($Name, ENT_QUOTES) . "</a><br/>\n";
	echo "<b>Fingerprint:</b><br/>" . chunk_split(strtoupper(htmlspecialchars($Fingerprint, ENT_QUOTES)), 4, " ") . "<br/>\n";
	echo "<b>Country Code:</b><br/>"; if($CountryCode == null){echo "Unknown";}else{echo htmlspecialchars($CountryCode, ENT_QUOTES);} echo "<br/>\n";
	echo "<b>Contact:</b><br/>"; if($Contact == null){echo "None Given";} else{echo htmlspecialchars($Contact, ENT_QUOTES);} echo "<br/>\n";
	echo "<b>Platform:</b><br/>" . htmlspecialchars($Platform, ENT_QUOTES) . "<br/>\n";
	echo "<b>IP Address:</b><br/>" . htmlspecialchars($IP, ENT_QUOTES) . "<br/>\n";
	echo "<b>Hostname:</b><br/>"; if ($IP == $Hostname){echo "Unavailable";} else{echo htmlspecialchars($Hostname, ENT_QUOTES);} echo "<br/>\n";
	echo "<b>Onion Router Port:</b><br/>" . htmlspecialchars($ORPort, ENT_QUOTES) . "<br/>\n";
	echo "<b>Directory Server Port:</b><br/>"; if($DirPort == 0){echo "None";} else {echo htmlspecialchars($DirPort, ENT_QUOTES);} echo "<br/>\n";
	echo "<b>Last Published Descriptor (GMT):</b><br/>" . htmlspecialchars($LastDescriptorPublished, ENT_QUOTES) . "<br/><br/>\n";
	echo "<b>Onion Key:</b><pre>" . htmlspecialchars($OnionKey, ENT_QUOTES) . "</pre>\n";
	echo "<b>Signing Key:</b><pre>" . htmlspecialchars($SigningKey, ENT_QUOTES) . "</pre>\n";
	echo "<b>Descriptor Signature:</b><pre>" . htmlspecialchars($DescriptorSignature, ENT_QUOTES) . "</pre>\n";
}
else {
	echo "Data source is a client, not a relay, therefore no detailled information is available";
}
?>
</td>
</tr>
</table>

<?php
$nsos_block = ob_get_clean();

// query block
ob_start();
?>
<table width='100%' cellspacing='0' cellpadding='0' class='displayTable' id='caqoTable'>
<tr>
<td class='HRN'>Custom / Advanced Query Options</td>
</tr>
<tr>
<td class='TRS'><br/>
<?php
echo "<form action='$Self' method='post'>\n";
echo "<b>Sort Router Listing By:</b><br/><span class='TRSM'>(Sorted-by column will be <i>italic</i>)<br/>(Column names can also be clicked to sort)</span><br/>\n";
echo "<select name='SR' class='BOX'>\n";
echo "<option value='Name'"; if ($SR == 'Name'){echo " selected='selected'";} echo ">Router Name</option>\n";
echo "<option value='Fingerprint'"; if ($SR == 'Fingerprint'){echo " selected='selected'";} echo ">Fingerprint</option>\n";
echo "<option value='CountryCode'"; if ($SR == 'CountryCode'){echo " selected='selected'";} echo ">Country Code</option>\n";
echo "<option value='Bandwidth'"; if ($SR == 'Bandwidth'){echo " selected='selected'";} echo ">Bandwidth</option>\n";
echo "<option value='Uptime'"; if ($SR == 'Uptime'){echo " selected='selected'";} echo ">Uptime</option>\n";
echo "<option value='LastDescriptorPublished'"; if ($SR == 'LastDescriptorPublished'){echo " selected='selected'";} echo ">Last Descriptor Published</option>\n";
echo "<option value='Hostname'"; if ($SR == 'Hostname'){echo " selected='selected'";} echo ">Hostname</option>\n";
echo "<option value='IP'"; if ($SR == 'IP'){echo " selected='selected'";} echo ">IP Address</option>\n";
echo "<option value='ORPort'"; if ($SR == 'ORPort'){echo " selected='selected'";} echo ">ORPort</option>\n";
echo "<option value='DirPort'"; if ($SR == 'DirPort'){echo " selected='selected'";} echo ">DirPort</option>\n";
echo "<option value='Platform'"; if ($SR == 'Platform'){echo " selected='selected'";} echo ">Platform</option>\n";
echo "<option value='Contact'"; if ($SR == 'Contact'){echo " selected='selected'";} echo ">Contact</option>\n";
echo "<option value='FAuthority'"; if ($SR == 'FAuthority'){echo " selected='selected'";} echo ">Authority</option>\n";
echo "<option value='FBadDirectory'"; if ($SR == 'FBadDirectory'){echo " selected='selected'";} echo ">Bad Directory</option>\n";
echo "<option value='FBadExit'"; if ($SR == 'FBadExit'){echo " selected='selected'";} echo ">Bad Exit</option>\n";
echo "<option value='FExit'"; if ($SR == 'FExit'){echo " selected='selected'";} echo ">Exit</option>\n";
echo "<option value='FFast'"; if ($SR == 'FFast'){echo " selected='selected'";} echo ">Fast</option>\n";
echo "<option value='FGuard'"; if ($SR == 'FGuard'){echo " selected='selected'";} echo ">Guard</option>\n";
echo "<option value='Hibernating'"; if ($SR == 'Hibernating'){echo " selected='selected'";} echo ">Hibernating</option>\n";
echo "<option value='FNamed'"; if ($SR == 'FNamed'){echo " selected='selected'";} echo ">Named</option>\n";
echo "<option value='FStable'"; if ($SR == 'FStable'){echo " selected='selected'";} echo ">Stable</option>\n";
echo "<option value='FRunning'"; if ($SR == 'FRunning'){echo " selected='selected'";} echo ">Running</option>\n";
echo "<option value='FValid'"; if ($SR == 'FValid'){echo " selected='selected'";} echo ">Valid</option>\n";
echo "<option value='FV2Dir'"; if ($SR == 'FV2Dir'){echo " selected='selected'";} echo ">V2Dir</option>\n";
echo "<option value='FHSDir'"; if ($SR == 'FHSDir'){echo " selected='selected'";} echo ">HSDir</option>\n";
echo "</select><br/><br/>\n";
echo "<b>Sort Order:</b><br/><span class='TRSM'>(Column names can also be clicked to toggle)</span><br/>\n";
echo "<select name='SO' class='BOX'>\n";
echo "<option value='Asc'"; if ($SO == 'Asc'){echo " selected='selected'";} echo ">Ascending</option>\n";
echo "<option value='Desc'"; if ($SO == 'Desc'){echo " selected='selected'";} echo ">Descending</option>\n";
echo "</select><br/><br/>\n";
echo "<b>Require Flags:</b><br/><span class='TRSM'>(Columns flagged YES will have <font color='#00dd00'>green</font> background)<br/>(Columns flagged NO will have <font color='#ff0000'>red</font> background)</span><br/>\n";
echo "<table width='100%' cellspacing='0' cellpadding='0' border='0' align='left'>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Authority:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FAuthority' value='OFF'"; if($FAuthority == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FAuthority' value='1'"; if($FAuthority == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FAuthority' value='0'"; if($FAuthority == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;BadDirectory:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FBadDirectory' value='OFF'"; if($FBadDirectory == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FBadDirectory' value='1'"; if($FBadDirectory == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FBadDirectory' value='0'"; if($FBadDirectory == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;BadExit:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FBadExit' value='OFF'"; if($FBadExit == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FBadExit' value='1'"; if($FBadExit == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FBadExit' value='0'"; if($FBadExit == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Exit:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FExit' value='OFF'"; if($FExit == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FExit' value='1'"; if($FExit == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FExit' value='0'"; if($FExit == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Fast:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FFast' value='OFF'"; if($FFast == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FFast' value='1'"; if($FFast == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FFast' value='0'"; if($FFast == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Guard:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FGuard' value='OFF'"; if($FGuard == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FGuard' value='1'"; if($FGuard == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FGuard' value='0'"; if($FGuard == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Hibernating:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FHibernating' value='OFF'"; if($FHibernating == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FHibernating' value='1'"; if($FHibernating == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FHibernating' value='0'"; if($FHibernating == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Named:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FNamed' value='OFF'"; if($FNamed == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FNamed' value='1'"; if($FNamed == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FNamed' value='0'"; if($FNamed == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Stable:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FStable' value='OFF'"; if($FStable == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FStable' value='1'"; if($FStable == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FStable' value='0'"; if($FStable == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Running:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FRunning' value='OFF'"; if($FRunning == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FRunning' value='1'"; if($FRunning == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FRunning' value='0'"; if($FRunning == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;Valid:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FValid' value='OFF'"; if($FValid == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FValid' value='1'"; if($FValid == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FValid' value='0'"; if($FValid == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;V2Dir:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FV2Dir' value='OFF'"; if($FV2Dir == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FV2Dir' value='1'"; if($FV2Dir == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FV2Dir' value='0'"; if($FV2Dir == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS'>&nbsp;HSDir:</td>\n";
echo "<td class='TRS'>\n";
echo "<input type='radio' name='FHSDir' value='OFF'"; if($FHSDir == 'OFF'){echo " checked='checked' />Off&nbsp;\n";}else{echo " />Off&nbsp;\n";}
echo "<input type='radio' name='FHSDir' value='1'"; if($FHSDir == '1'){echo " checked='checked' />Yes&nbsp;\n";}else{echo " />Yes&nbsp;\n";}
echo "<input type='radio' name='FHSDir' value='0'"; if($FHSDir == '0'){echo " checked='checked' />No\n";}else{echo " />No\n";}
echo "</td>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='TRS' colspan='4'><br/>\n";
echo "<b>Advanced Search:</b><br/><span class='TRSM'>(Clear search box to disable)</span><br/>\n";
echo "<select name='CSField' class='BOX'>\n";
echo "<option value='Fingerprint'"; if ($CSField == 'Fingerprint'){echo " selected='selected'";} echo ">Fingerprint</option>\n";
echo "<option value='Name'"; if ($CSField == 'Name'){echo " selected='selected'";} echo ">Router Name</option>\n";
echo "<option value='CountryCode'"; if ($CSField == 'CountryCode'){echo " selected='selected'";} echo ">Country Code</option>\n";
echo "<option value='Bandwidth'"; if ($CSField == 'Bandwidth'){echo " selected='selected'";} echo ">Bandwidth (KB/s)</option>\n";
echo "<option value='Uptime'"; if ($CSField == 'Uptime'){echo " selected='selected'";} echo ">Uptime (Days)</option>\n";
echo "<option value='LastDescriptorPublished'"; if ($CSField == 'LastDescriptorPublished'){echo " selected='selected'";} echo ">Last Descriptor Published</option>\n";
echo "<option value='IP'"; if ($CSField == 'IP'){echo " selected='selected'";} echo ">IP Address</option>\n";
echo "<option value='Hostname'"; if ($CSField == 'Hostname'){echo " selected='selected'";} echo ">Hostname</option>\n";
echo "<option value='ORPort'"; if ($CSField == 'ORPort'){echo " selected='selected'";} echo ">Onion Router Port</option>\n";
echo "<option value='DirPort'"; if ($CSField == 'DirPort'){echo " selected='selected'";} echo ">Directory Server Port</option>\n";
echo "<option value='Platform'"; if ($CSField == 'Platform'){echo " selected='selected'";} echo ">Platform</option>\n";
echo "<option value='Contact'"; if ($CSField == 'Contact'){echo " selected='selected'";} echo ">Contact</option>\n";
echo "</select>\n";
echo "<select name='CSMod' class='BOX'>\n";
echo "<option value='Equals'"; if ($CSMod == 'Equals'){echo " selected='selected'";} echo ">Equals</option>\n";
echo "<option value='Contains'"; if ($CSMod == 'Contains'){echo " selected='selected'";} echo ">Contains</option>\n";
echo "<option value='LessThan'"; if ($CSMod == 'LessThan'){echo " selected='selected'";} echo ">Is Less Than</option>\n";
echo "<option value='GreaterThan'"; if ($CSMod == 'GreaterThan'){echo " selected='selected'";} echo ">Is Greater Than</option>\n";
echo "</select><br/>\n";
echo "<input type='text' name='CSInput' class='BOX' maxlength='128' size='45' value='" . htmlspecialchars($CSInput ? $CSInput : '', ENT_QUOTES) . "' /><br/><br/>\n";
echo "&nbsp;&nbsp;<input type='submit' value='Apply Options' /><br/><br/>\n";
echo "</td>\n";
echo "</tr>\n";
echo "</table>\n";
echo "</form>\n";
?>
</td>
</tr>
</table>

<?php
$query_block = ob_get_clean();

// legend block
ob_start();
?>
<table width='300px' cellspacing='0' cellpadding='0' class='displayTable' id='lgndTable'>
<tr><td class='HRN'>Legend:</td></tr>
<tr class='r'><td style='padding: 1px;'>Router is okay</td></tr>
<tr class='R'><td style='padding: 1px;'>Router is hibernating</td></tr>
<tr class='d'><td style='padding: 1px;'><img src='/img/routerdown.png' alt=' router is down' title='Router is currently down'/>Router is currently down</td></tr>
<tr class='B'><td style='padding: 1px;'>Router is a bad exit node</td></tr>
</table>

<?php
$legend_block = ob_get_clean();

// appserver block
ob_start();
?>
<table cellspacing='0' cellpadding='0' class='displayTable' width='500px' id='asdTable'>
<tr>
<td class='HRN' colspan='2'>Application Server Details</td>
</tr>

<?php

echo "<tr>\n";
echo "<td class='TRAR'><b>Cache Last Updated (Local Server Time):</b></td>\n";
echo "<td class='TRS'>$LastUpdate $LocalTimeZone</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td class='TRAR'><b>Last Update Cycle Processing Time (Seconds):</b></td>\n";
echo "<td class='TRS'>$LastUpdateElapsed</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td class='TRAR'><b>Number of Routers In Cache:</b></td>\n";
echo "<td class='TRS'>$RouterCount</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td class='TRAR'><b>Number of Descriptors In Cache:</b></td>\n";
echo "<td class='TRS'>$DescriptorCount</td>\n";
echo "</tr>\n";

// Get script end time
$TimeStop = microtime(true);

echo "<tr>\n";
echo "<td class='TRAR'><b>Approximate Page Generation Time (Seconds):</b></td>\n";
echo "<td class='TRS'>" . (round(($TimeStop - $TimeStart),4)) . "</td>\n";
echo "</tr>\n";

?>
</table>

<?php
$appserver_block = ob_get_clean();

$context = [
	'routers' => $routers,
	'columns_active' => $ColumnList_ACTIVE,
	'sr' => $SR,
	'so' => $SO,
	'page' => $Page,
	'total_pages' => $TotalPages,
	'total_results' => $TotalResults,
	'rows_per_page' => (int)$RowsPerPage,
	'self' => $Self,
	'country_codes' => $country_codes,
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
	'FHSDir' => $FHSDir,
	'CSField' => $CSField,
	'CSMod' => $CSMod,
	'CSInput' => $CSInput,
	'stats_block' => $stats_block,
	'nsos_block' => $nsos_block,
	'query_block' => $query_block,
	'legend_block' => $legend_block,
	'appserver_block' => $appserver_block,
	'onion_service' => $onion_service,
	'PositiveMatch_IP' => $PositiveMatch_IP,
	'RemoteIP' => $RemoteIP,
	'Count' => $Count,
	'TorNodeName' => $TorNodeName ?? [],
	'TorNodeFP' => $TorNodeFP ?? [],
	'PositiveMatch_ExitPolicy' => $PositiveMatch_ExitPolicy ?? [],
	'Hidden_Service_URL' => $Hidden_Service_URL,
	'SourceFingerprint' => $Fingerprint,
	'SourceName' => $Name,
	'SourceCountryCode' => $CountryCode,
	'SourceContact' => $Contact,
	'SourcePlatform' => $Platform,
	'SourceIP' => $IP,
	'SourceHostname' => $Hostname,
	'SourceORPort' => $ORPort,
	'SourceDirPort' => $DirPort,
	'SourceLastDescriptorPublished' => $LastDescriptorPublished,
	'SourceOnionKey' => $OnionKey,
	'SourceSigningKey' => $SigningKey,
	'SourceDescriptorSignature' => $DescriptorSignature,
	'SourceFingerprint_formatted' => chunk_split(strtoupper($Fingerprint), 4, " "),
	'LastUpdate' => $LastUpdate,
	'LocalTimeZone' => $LocalTimeZone,
	'LastUpdateElapsed' => $LastUpdateElapsed,
	'RouterCount' => $RouterCount,
	'DescriptorCount' => $DescriptorCount,
	'page_generation_time' => round((microtime(true) - $TimeStart), 4),
];

render('index.html.twig', $context);

// Close connection
$mysqli->close();

// Register session variable
if (!isset($_SESSION['IndexVisited']))
{
	$_SESSION['IndexVisited'] = 1;
}
