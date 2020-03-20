<?php

# todo: via post erhalten
$cfg_auth_passwords = array();
$cfg_auth_passwords[] = 'marie,marie';
$cfg_auth_passwords[] = 'vierzig';
$cfg_auth_passwords[] = 'johnl17';

if (!headers_sent()) header('Content-Type: text/plain');
echo output(__DIR__ . '/.volcano_db/*', $cfg_auth_passwords);

# ---

# todo fut: oop
function check_auth($auth_passwords, $auth_objs) {
	foreach ($auth_objs as &$auth_obj) {
		$auth_method = $auth_obj[0];
		$auth_data   = $auth_obj[1];

		$auth_method = strtolower($auth_method);

		foreach ($auth_passwords as $p) {
			if ($auth_method == 'plain') {
				if ($p == $auth_data) return true;
			} else if ($auth_method == 'md5') {
				if (md5($p) == strtolower($auth_data)) return true;
			} else if ($auth_method == 'md5-salt') {
				$auth_data_ary = explode(':', $auth_data, 2);
				$auth_data_salt = $auth_data_ary[0];
				$auth_data_hash = $auth_data_ary[1];
				if (md5($auth_data_salt.$p) == strtolower($auth_data_hash)) return true;
			} else if ($auth_method == 'sha1') {
				if (sha1($p) == strtolower($auth_data)) return true;
			} else if ($auth_method == 'sha1-salt') {
				$auth_data_ary = explode(':', $auth_data, 2);
				$auth_data_salt = $auth_data_ary[0];
				$auth_data_hash = $auth_data_ary[1];
				if (sha1($auth_data_salt.$p) == strtolower($auth_data_hash)) return true;
			} else {
				# todo exception
			}
		}
		unset($p);
	}

	return false;
}

function output($wildcard, $cfg_auth_passwords = array()) {
	$file = file_glob($wildcard, FILE_IGNORE_NEW_LINES);

	$auth_array = array();
	foreach ($file as &$f) {
		preg_match_all('@^\s*([^:\s]+):(\S*)(\.){0,1}([^.\s]*)\s+READ-AUTH\s+([^:\s]+):(\S+)\s*$@isU', $f, $m, PREG_SET_ORDER);

		foreach ($m as $x) {
			$nid = $x[1];
			$parent = $x[2];
			$dot = $x[3];
			$child = $x[4];
			$auth_method = $x[5];
			$auth_data = $x[6];

			$regex = '';
			if ($parent == '' && $child == '') {
				$regex = '@^\s*'.preg_quote($nid, '@').':(.*)$@isU';
				$replace = '# CONFIDENTIAL MATERIAL REDACTED DUE TO MISSING AUTHENTIFICATION';
				$auth_array[$regex][$replace][] = array($auth_method, $auth_data);
			} else {
				$regex = '@^\s*('.preg_quote($nid, '@').':'.preg_quote($parent, '@').')\s+(DELEGATION)\s+('.preg_quote($child, '@').')(|\s+.*)$@isU';
				# todo option ob man delegation pub oder nicht pub machen will
				$replace = '\1 \2 ???';
				$auth_array[$regex][$replace][] = array($auth_method, $auth_data);

				$regex = '@^\s*'.preg_quote($nid, '@').':'.preg_quote($parent.$dot.$child, '@').'\s+(.*)$@isU';
				$replace = '# CONFIDENTIAL MATERIAL REDACTED DUE TO MISSING AUTHENTIFICATION';
				$auth_array[$regex][$replace][] = array($auth_method, $auth_data);
			}
		}
	}

	global $cfg_auth_passwords;

	$forbidden_regex = array();
	foreach ($auth_array as $search => &$tmp1) {
		foreach ($tmp1 as $replace => &$auth_objs) {
			if (!check_auth($cfg_auth_passwords, $auth_objs)) {
				$forbidden_regex[$search] = $replace;
			}
		}
	}

	var_dump($forbidden_regex);

	foreach ($file as &$f) {
		foreach ($forbidden_regex as $search => &$replace) {
			$num = 0;
			$f = preg_replace($search, $replace, $f, -1, $num);
			if ($num > 0) {echo '!!!'; break;}
		}
	}

	return implode("\n", $file);
}

function file_glob($wildcard, $flags = 0, $context = null) {
	$files = glob($wildcard);
	sort($files);

	$res = array();
	foreach ($files as $file) {
		$bn = basename($file);
		if ($bn[0] == '.') continue; // ., .., or .htaccess
		$res = array_merge($res, file($file, $flags, $context));
	}
	unset($file);
	unset($files);

	return $res;
}

?>
