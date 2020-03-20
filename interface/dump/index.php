<?php

set_time_limit(0);

include_once __DIR__ . '/../../includes/oid_plus.inc.php';
include_once __DIR__ . '/../../includes/oid_utils.inc.php';
include_once __DIR__ . '/../../includes/config.inc.php';
include_once __DIR__ . '/../../includes/gui.inc.php';

# TODO: HTML header etc

$db = new OIDPlus(__DIR__ . '/../../db/local.conf', true);
$db->addDir(__DIR__ . '/../../db');

$x = $db->listAllOIDs('.');
foreach ($x as $oid) {
	$query = $oid;
	if (!$db->oidDescribed($query)) continue;

	echo "<h2>$oid</h2>";
	$cont = $db->showSingleOID($oid, '', OIDPlus::SEG_OIDDATA, false);
	$cont = $db->filterRedactedEntries($cont);
	showHTML($cont, $db, false);
}
