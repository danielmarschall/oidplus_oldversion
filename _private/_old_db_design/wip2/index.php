<?php

# Volcano Format

error_reporting(E_ALL | E_NOTICE | E_STRICT | E_DEPRECATED);

class VOF_Category {
	public $nid;
	public $localrootElement;
	public $localrootFile;
	public $authrootElement;
	public $authrootFile;

	public function __construct($nid, $localrootElement, $localrootFile, $authrootElement, $authrootFile) {
		$this->nid = $nid;
		$this->localrootElement = $localrootElement;
		$this->localrootFile = $localrootFile;
		$this->authrootElement = $authrootElement;
		$this->authrootFile = $authrootFile;
	}
}

$categories = array();
include __DIR__ . '/local_config.inc.php';



# Aufruf:
# nid, obj, route[]
print_r(show_obj('1.3.6.1.4.1.37476.9999.1.2.3.4', 'oid', array(
'1.3.6.1.4.1.37476',
'1.3.6.1.4.1.37476.9999',
'1.3.6.1.4.1.37476.9999.1',
'1.3.6.1.4.1.37476.9999.1.2',
'1.3.6.1.4.1.37476.9999.1.2.3'
)));
die();



$req_cat = $_GET['cat']; # todo als hash (nid, localelement)
$req_obj = $_GET['obj'];

if ($req_cat == '') {
	// List all categories
	list_nids();
} else {
	// List specific object
	$cat = get_cat($req_cat);
	$title = $cat->nid . ' - ' . $req_obj;
	echo "<h1>$title</h1>";
	$localRoot = $cat->localrootElement;
}

function list_nids($categories) {
	echo '<ul>';
	foreach ($categories as $x) {
		$id = cat_id($x);
		$desc = $x->nid;
		echo '<li><a href="?cat='.$id.'">'.$desc.'</a></li>';
	}
	echo '</ul>';
}

function cat_id($cat) {
	return sha1($cat->nid.':'.$cat->localrootElement);
}

function get_cat($categoryId, $categories) {
	foreach ($categories as $cat) {
		if ($categoryId == cat_id($cat)) return $cat;
	}
	return false;
}


function show_obj($obj, $nid, $route) {
	global $categories;
	foreach ($categories as $c) {
		if (($c->nid == $nid) && ($c->localrootElement == $route[0])) {
			$cur_obj = $c->localrootElement;
			$cur_fil = $c->localrootFile;
		}
	}
	if (!isset($cur_obj)) return false;
	if (!isset($cur_fil)) return false;

	foreach ($route as $i => $r) {
		if ($i == 0) continue;
		$x = search_delegation_file($cur_fil, $nid, $cur_obj, $r);
		if ($x === false) return false;
		if ($x != '') $cur_file = $x;
		$cur_obj = $r;
	}

	return array($cur_fil, $cur_obj);
}

function search_delegation_file($file, $nid, $rootobj, $childobj) {
	$cont = file($file); # todo: cache

	echo "R=$rootobj, C=$childobj => ";
	if (substr($childobj.'.', 0, strlen($rootobj)+1) == $rootobj.'.') {
		$childobj = substr($childobj, strlen($rootobj)+1);
	}
	echo "$childobj\n";

	foreach ($cont as $c) {
		preg_match_all("@^\s*$nid:$rootobj\s+delegation\s+$childobj\s*(.+)\$@", $c, $m);
		if (!isset($m[1][0])) continue;
		$x = $m[1][0];
		$x = trim($x);
		if ($x == '<here>') $x = '';
		return $x;
	}
	return false;
}

?>
