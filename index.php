<?php

include_once __DIR__ . '/includes/oid_plus.inc.php';
include_once __DIR__ . '/includes/oid_utils.inc.php';
include_once __DIR__ . '/includes/config.inc.php';
include_once __DIR__ . '/includes/gui.inc.php';

define('START_PAGE', 'welcome');

$db = new OIDPlus(__DIR__ . '/db/local.conf', true);

// The inclusion of get_current_user() solves a Problem with suPHP, when multiple users run different instances of OID+ with the same SystemID
session_name('OIDPLUS_SESS_'.sha1(strtolower($db->getSystemID()).get_current_user()));
session_start();

$title = $db->getConfigValue('webinterface_title');
if ($title === false) $title = 'OID+ web interface';

$systemID = $db->getConfigValue('system_unique_id');

try {
	$db->addDir(__DIR__ . '/db');
	echo page_header($title, $systemID); // TODO: dynamischer titel, z.B. die aktuell angezeigte OID
} catch (VolcanoException $e) {
	echo showException($e);
	exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : START_PAGE;
$query  = isset($_REQUEST['query'])  ? $_REQUEST['query']  : '';

# ---

if (isset($_REQUEST['new_auth_token'])) {
	if (!isset($_SESSION['auth_tokens'])) {
		$_SESSION['auth_tokens'] = array();
	}
	if (!in_array($_REQUEST['new_auth_token'], $_SESSION['auth_tokens'])) {
		$_SESSION['auth_tokens'][] = $_REQUEST['new_auth_token'];
	}
} else if (isset($_REQUEST['delete_all_auth_tokens'])) {
	unset($_SESSION['auth_tokens']);
}

if (isset($_SESSION['auth_tokens'])) {
	$auth_tokens = ' #'.implode(',',$_SESSION['auth_tokens']);
} else {
	$auth_tokens = '';
}

$auth_token_count = (isset($_SESSION['auth_tokens'])) ? count($_SESSION['auth_tokens']) : 0;

# TODO: auth tokens schreiben bei allen "executed query" usw?

# ---

echo '<form action="index.php" method="get">';
echo '<table border="0" cellpadding="5" cellspacing="0" width="100%" id="headertable">';

echo '<tr>';
echo '<td colspan="2" align="center">';
	echo '<h1>'.htmlentities($title).'</h1>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<td>';
	if ($action != 'welcome') echo '<a href="?action=welcome">'; else echo '<b>';
	echo 'Welcome';
	if ($action != 'welcome') echo '</a>'; else echo '</b>';
	echo ' | ';

	if ($action != 'roots') echo '<a href="?action=roots">'; else echo '<b>';
	echo 'Roots';
	if ($action != 'roots') echo '</a>'; else echo '</b>';
	echo ' (' . $db->count_roots() . ')';
	echo ' | ';

	if ($action != 'indexes') echo '<a href="?action=indexes">'; else echo '<b>';
	echo 'Indexes';
	if ($action != 'indexes') echo '</a>'; else echo '</b>';
	echo ' (' . $db->count_indexes() . ')';
	echo ' | ';

	if ($action != 'list_all') echo '<a href="?action=list_all">'; else echo '<b>';
	echo 'List all';
	if ($action != 'list_all') echo '</a>'; else echo '</b>';
	echo ' (' . $db->count_oids() . ')';
	echo ' | ';

	if ($action != 'auth_tokens') echo '<a href="?action=auth_tokens">'; else echo '<b>';
	echo 'Auth tokens';
	if ($action != 'auth_tokens') echo '</a>'; else echo '</b>';
	echo ' ('.$auth_token_count.')';
echo '</td>';
echo '<td align="right">';
	echo '<input type="hidden" name="action" value="query" />';

	if ($action == 'roots') {
		$query = 'oidplus:!listRoots';
	} else if ($action == 'indexes') {
		$query = 'oidplus:!listIndexes';
	} else if ($action == 'list_all') {
		$query = 'oidplus:!list';
	} else if ($action == 'help') {
		$query = 'help';
	} else if ($action == 'show_oid') {
		if (isset($_REQUEST['oid'])) {
			$query = 'oidplus:'.$_REQUEST['oid'];
		} else {
			die('</td></tr></table><h2>Invalid request</h2><p>Paramter "oid" is missing</p>'.page_footer());
		}
	} else if ($action == 'show_index') {
		if ($_REQUEST['index']) {
			if (isset($_REQUEST['ns'])) {
				$query = $_REQUEST['ns'].':'.$_REQUEST['index'];
			} else {
				$query = 'oidplus:'.$_REQUEST['index'];
			}
		} else {
			die('</td></tr></table><h2>Invalid request</h2><p>Paramter "index" is missing</p>'.page_footer());
		}
	}

	echo 'Manual query (<a href="?action=help">help</a>): <input size="50" type="text" name="query" value="'.htmlentities($query).'" />'."\n";
	echo '<input type="submit" value="OK" />';
echo '</td>';
echo '</tr>';
echo '</table>';
echo '</form>';

# ---

try {
	# TODO: codeduplikate vermeiden
	if ($action == 'welcome') {
		# TODO
		include 'welcome.php';
	} else if ($action == 'uuid_info') {
		$uuid = $_REQUEST['uuid'];

		if (!preg_match('@^([A-Fa-f0-9]{8}\\-[A-Fa-f0-9]{4}\\-[A-Fa-f0-9]{4}\\-[A-Fa-f0-9]{4}\\-[A-Fa-f0-9]{12})$@', $uuid, $m)) {
			echo "\n\n<h2>Information about an UUID</h2>\n\n";

			echo '<p><font color="red">';
			echo 'Error: '.htmlentities($uuid).' is not a valid UUID.';
			echo '</font></p>';
		} else {
			echo "\n\n<h2>Information about UUID $uuid</h2>\n\n";

			$url = 'https://misc.daniel-marschall.de/tools/uuid_mac_decoder/interprete_uuid.php?uuid='.$uuid;

			echo '<p class="green">Querying <a href="'.$url.'" target="_blank">'.htmlentities($url).'</a></p>';

			$c = @file_get_contents($url);

			if (preg_match('@<pre>(.*)</pre>@ismU', $c, $m)) {
				echo showHTML($m[1], $db);
			} else {
				echo '<p><font color="red">';
				echo 'Error while parsing <a href="'.$url.'" target="_blank">'.htmlentities($url).'</a>';
				echo '</font></p>';
			}
		}

		$query = '.'.uuid_to_oid($uuid);
		if ($db->oidDescribed($query)) {
			echo "\n\n<h2>Information about UUID OID ".htmlentities($query)."</h2>\n\n";
			echo queryInfo($query);
			ob_start();
			$db->query($query.$auth_tokens);
			$cont = ob_get_contents();
			ob_end_clean();
			echo showHTML($cont, $db);
		}

		# Alle OIDs durchgehen und schauen, ob namebased irgendwo passt
		$x = $db->listAllOIDs('.');
		foreach ($x as $oid) {
			$query = $oid;
			if (gen_uuid_md5_namebased(UUID_NAMEBASED_NS_OID, $oid) == $uuid) {
				echo "\n\n<h2>Information about ".htmlentities($query)." (MD5 namebased UUID)</h2>\n\n";
				echo queryInfo($query);
				ob_start();
				$db->query($query.$auth_tokens);
				$cont = ob_get_contents();
				ob_end_clean();
				echo showHTML($cont, $db);
			}
			if (gen_uuid_sha1_namebased(UUID_NAMEBASED_NS_OID, $oid) == $uuid) {
				echo "\n\n<h2>Information about ".htmlentities($query)." (SHA1 namebased UUID)</h2>\n\n";
				echo queryInfo($query);
				ob_start();
				$db->query($query.$auth_tokens);
				$cont = ob_get_contents();
				ob_end_clean();
				echo showHTML($cont, $db);
			}
		}
	} else if ($action == 'roots') {
		echo "\n\n<h2>Roots</h2>\n\n";
		echo queryInfo($query);
		$r = $db->findRoots();
		foreach ($r as $root) {
			echo "\n\n<h2>Root $root</h2>\n\n";
			echo queryInfo("oidplus:$root");
			ob_start();
			$db->query('oidplus:'.$root.$auth_tokens);
			$cont = ob_get_contents();
			ob_end_clean();
			echo showHTML($cont, $db);
		}
	} else if ($action == 'indexes') {
		echo "\n\n<h2>Indexes</h2>\n\n";
		echo queryInfo($query);
		ob_start();
		$db->query($query.$auth_tokens);
		$cont = ob_get_contents();
		ob_end_clean();
		echo showHTML($cont, $db);
	} else if ($action == 'list_all') {
		echo "\n\n<h2>List all</h2>\n\n";
		echo queryInfo($query);
		ob_start();
		$db->query($query.$auth_tokens);
		$cont = ob_get_contents();
		ob_end_clean();
		echo showHTML($cont, $db);
	} else if ($action == 'help') {
		echo "\n\n<h2>Help</h2>\n\n";
		echo queryInfo($query);
		ob_start();
		$db->query($query.$auth_tokens);
		$cont = ob_get_contents();
		ob_end_clean();
		echo showHTML($cont, $db);
	} else if ($action == 'show_oid') {
		echo "\n\n<h2>OID ".htmlentities($_REQUEST['oid'])."</h2>\n\n";
		echo queryInfo($query);
		ob_start();
		$db->query($query.$auth_tokens);
		$cont = ob_get_contents();
		ob_end_clean();
		echo showHTML($cont, $db);
	} else if ($action == 'show_index') {
		echo "\n\n<h2>Index ".htmlentities($_REQUEST['index'])."</h2>\n\n";
		echo queryInfo($query);
		ob_start();
		$db->query($query.$auth_tokens);
		$cont = ob_get_contents();
		ob_end_clean();
		echo showHTML($cont, $db);
	} else if ($action == 'query') {
		echo "\n\n<h2>Query ".htmlentities($query)."</h2>\n\n";
		echo queryInfo($query);
		ob_start();
		$db->query($query.$auth_tokens);
		$cont = ob_get_contents();
		ob_end_clean();
		echo showHTML($cont, $db);
	} else if ($action == 'auth_tokens') {
		echo "\n\n<h2>Auth tokens</h2>\n\n";

		echo '<form action="index.php" method="get">';
		echo '<input type="hidden" name="action" value="'.htmlentities($action).'" />';

		if ($auth_token_count == 0) {
			echo "<p>No auth tokens have been added.</p>";
		} else {
			echo "<p><font color=\"red\">Registered auth tokens: $auth_token_count</font></p>";
		}

		echo '<p>Add new auth token: <input type="password" name="new_auth_token" value="" />'."\n";
		echo '<input type="submit" value="Add"></p>';

		echo '<p><a href="?action='.htmlentities($action).'&amp;delete_all_auth_tokens=1">Delete all tokens</a></p>';

		echo '</form>';
	} else {
		echo '<p><font color="red">';
		echo 'Unknown command "'.htmlentities($action).'"';
		echo '</font></p>';
	}
} catch (VolcanoException $e) {
	echo showException($e);
	exit;
}

# ---

session_write_close();
echo page_footer();
