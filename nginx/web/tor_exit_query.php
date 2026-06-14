<?php

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once('init.php');

$Self = $_SERVER['PHP_SELF'];

$QueryIP = null;
$DestinationIP = null;
$DestinationPort = null;
$QueryIPDBCount = null;
$PositiveMatch_IP = 0;
$PositiveMatch_ExitPolicy = null;
$TorNodeName = null;
$TorNodeFP = null;
$TorNodeExitPolicy = null;

$Count = 0;

// Function Declarations
function IsIPInSubnet($IP,$Subnet)
{
	// Credit for the parts of the code in this function:
	// This code used in this function was found on the PHP.net website's 'IP2Long' function page.
	// It was posted by 'Ian B' on '24-Dec-2006 04:22'.

	/* always return true if subnet is wildcard */
	if ($Subnet == '*')
	{
		return 1;
	}

	/* always return true if ip is an exact match as is */
	if ($Subnet == $IP)
	{
		return 1;
	}

	/* always return false if only an ip was provided, and it's not an exact match */
	if (strpos($Subnet, '/') === FALSE)
	{
		return 0;
	}

       /* get the base and the bits from the subnet */
       list($base, $bits) = explode('/', $Subnet);

       /* now split it up into it's classes */
       list($a, $b, $c, $d) = explode('.', $base);

       /* now do some bit shifting/switching to convert to ints */
       $i = ((int)$a << 24) + ((int)$b << 16) + ((int)$c << 8) + (int)$d;
       $mask = (int)$bits == 0 ? 0 : (~0 << (32 - (int)$bits));

       /* here's our lowest int */
       $low = $i & $mask;

       /* here's our highest int */
       $high = $i | (~$mask & 0xFFFFFFFF);

       /* now split the ip we're checking against up into classes */
       list($a, $b, $c, $d) = explode('.', $IP);

       /* now convert the ip we're checking against to an int */
       $check = ((int)$a << 24) + ((int)$b << 16) + ((int)$c << 8) + (int)$d;

       /* if the ip is within the range, including highest/lowest values, then it's within the subnet range */
       if ($check >= $low && $check <= $high)
	{
		return 1;
	}
       else
	{
		return 0;
	}
}

// Read in submitted variables
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_POST["QueryIP"]))
	{
		$QueryIP = $_POST["QueryIP"];
	}
	if (isset($_POST["DestinationIP"]))
	{
		$DestinationIP = $_POST["DestinationIP"];
	}
	if (isset($_POST["DestinationPort"]))
	{
		$DestinationPort = $_POST["DestinationPort"];
	}
}

// Variable scrubbing
if ($QueryIP != null)
{
	if (strlen($QueryIP) > 15)
	{
		$QueryIP = null;
	}
	else
	{
		$QueryIP = $mysqli->escape_string($QueryIP);
	}

	$QueryIP_Long = ip2long($QueryIP);
	if ($QueryIP_Long == -1 || $QueryIP_Long === FALSE)
	{
		$QueryIP = null;
	}
	else
	{
		$QueryIP = long2ip($QueryIP_Long);
	}
}

if ($DestinationIP != null)
{
	if (strlen($DestinationIP) > 15)
	{
		$DestinationIP = null;
	}
	else
	{
		$DestinationIP = $mysqli->escape_string($DestinationIP);
	}

	$DestinationIP_Long = ip2long($DestinationIP);
	if ($DestinationIP_Long == -1 || $DestinationIP_Long === FALSE)
	{
		$DestinationIP = null;
	}
	else
	{
		$DestinationIP = long2ip($DestinationIP_Long);
	}
}

if ($DestinationPort != null)
{
	if (strlen($DestinationPort) > 5)
	{
		$DestinationPort = null;
	}
	else
	{
		$DestinationPort = $mysqli->escape_string($DestinationPort);
	}

	if 	(
		!is_numeric($DestinationPort) 	||
		intval($DestinationPort) < 0	||
		intval($DestinationPort) > 65535
		)
	{
		$DestinationPort = null;
	}
}

if ($QueryIP != null)
{
	// Determine if query IP exists in database as a Tor server
	$query = "select count(*) as Count from $ActiveNetworkStatusTable where IP = '$QueryIP'";
	$record = db_query_single_row($query);

	$QueryIPDBCount = $record['Count'];

	if ($QueryIPDBCount > 0)
	{
		$PositiveMatch_IP = 1;
	}

	// Get name, fingerprint, and exit policy of Tor node(s) if match was found and Destination IP/Port was specified, look for match in ExitPolicy
	if ($PositiveMatch_IP == 1 && $DestinationIP != null && $DestinationPort != null)
	{
		$query = "select $ActiveNetworkStatusTable.Name, $ActiveNetworkStatusTable.Fingerprint, $ActiveDescriptorTable.ExitPolicySERDATA from $ActiveNetworkStatusTable inner join $ActiveDescriptorTable on $ActiveNetworkStatusTable.Fingerprint = $ActiveDescriptorTable.Fingerprint where $ActiveNetworkStatusTable.IP = '$QueryIP'";
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
				list($Subnet,$PortLine) = explode(':', $NetworkLine);
				$Port = explode(',', $PortLine);

				// Find out if Destination IP user provided is a match for the subnet specified on this ExitPolicy line
				if (IsIPInSubnet($DestinationIP,$Subnet) == 1)
				{
					// Determine if port is also a match
					foreach($Port as $CurrentPortExpression)
					{
						// Handle condition where port is a '*' character (Port always matches)
						if ($CurrentPortExpression == '*')
						{
							if ($Condition == 'accept')
							{
								$PositiveMatch_ExitPolicy[$Count] = 1;
								break 2;
							}
							else if ($Condition == 'reject')
							{
								$PositiveMatch_ExitPolicy[$Count] = 0;
								break 2;
							}
						}

						// $CurrentPortExpression is a range of ports
						if(strpos($CurrentPortExpression, '-') !== FALSE)
						{
							list($LowerPort,$UpperPort) = explode('-', $CurrentPortExpression);

							if (($DestinationPort >= $LowerPort && $DestinationPort <= $UpperPort) && ($Condition == 'accept'))
							{
								$PositiveMatch_ExitPolicy[$Count] = 1;
								break 2;
							}
							else if (($DestinationPort >= $LowerPort && $DestinationPort <= $UpperPort) && ($Condition == 'reject'))
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
							if (($DestinationPort == $CurrentPortExpression) && ($Condition == 'accept'))
							{
								$PositiveMatch_ExitPolicy[$Count] = 1;
								break 2;
							}
							else if (($DestinationPort == $CurrentPortExpression) && ($Condition == 'reject'))
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
	// Get only name and fingerprint if match was found but Destination IP/Port were not specified
	else if ($PositiveMatch_IP == 1)
	{
		$query = "select $ActiveNetworkStatusTable.Name, $ActiveNetworkStatusTable.Fingerprint from $ActiveNetworkStatusTable where $ActiveNetworkStatusTable.IP = '$QueryIP'";
		$result = $mysqli->query($query);
		if(!$result) {
			die_503('Query failed: ' . $mysqli->error);
		}

		while ($record = $result->fetch_assoc())
		{
			$Count++;

			$TorNodeName[$Count] = $record['Name'];
			$TorNodeFP[$Count] = $record['Fingerprint'];
		}
		$result->free();
	}
}

$pageTitle = "Tor Exit Query";

$context = [
	'Self' => $Self,
	'QueryIP' => $QueryIP,
	'DestinationIP' => $DestinationIP,
	'DestinationPort' => $DestinationPort,
	'PositiveMatch_IP' => $PositiveMatch_IP,
	'PositiveMatch_ExitPolicy' => $PositiveMatch_ExitPolicy ?? [],
	'TorNodeName' => $TorNodeName ?? [],
	'TorNodeFP' => $TorNodeFP ?? [],
	'Count' => $Count,
];

render('tor_exit_query.html.twig', $context);

// Close connection
$mysqli->close();
