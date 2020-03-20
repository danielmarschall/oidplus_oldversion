<?php

#xxx TODO: debug
#$argc++;
#$argv[] = 'oidplus:!listRoots';


# TODO: try to find out namespace!


if (php_sapi_name() == 'cli') {
	header('HTTP/1.1 400 Bad Request');
	echo "Error: This script can only run in webserver mode\n"; # TODO: +STDERR?
	exit(2);
}

include_once __DIR__ . '/includes/oid_plus.inc.php';

header('Content-Type:text/plain');

$db = new OIDPlus(__DIR__ . '/db/local.conf', true);

$title = 'OID+ web interface [BETA]';

try {
	$db->addDir(__DIR__ . '/db');
} catch (VolcanoException $e) {
	header('HTTP/1.1 500 Internal Server Error');
	$title = 'Database error';
	$msg = $e->getMessage();
	$msg = str_replace(__DIR__,  '.', $msg);
	echo "$title\n\n";
	echo "An internal error occurred while reading the Volcano database. Please contact the administrator and try again later.\n\n"; # TODO: +STDERR?
	echo "Error message:\n\n";
	echo "$msg\n";
	exit;
}

if (!isset($_REQUEST['query'])) {
	echo "Syntax: ?query=<query>\n";
	exit(2);
}

#  echo "$title\n\n";

$args = $_REQUEST['query'];

$db->query($args);
