<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';
require_once __DIR__ . '/../../includes/uuid_utils.inc.php';

// TODO: use oidinfo api phps
// TODO: output warning if OID is illegal!

class OIDInfoFieldExtender implements OIDPlusFieldExtenders {
	public static function getURL($oid) {
		if ($oid[0] == '.') {
			$oid = substr($oid, 1);
		}

		return 'http://www.oid-info.com/get/'.$oid;
	}

	// currently not used
	public static function oidMayBeCreated($oid) {
		if ($oid[0] == '.') {
			$oid = substr($oid, 1);
		}

		# Ping API
		$v = @file_get_contents('https://www.viathinksoft.de/~daniel-marschall/oid-repository/ping_oid.php?oid='.$oid);
		$v = substr($v, 1, 1);
		if (trim($v) === '1') return true;
		if (trim($v) === '0') return false;

		// TODO: exception
		return null;
	}

	public static function oidAvailable($oid) {
		if ($oid[0] == '.') {
			$oid = substr($oid, 1);
		}

		# Ping API
		$v = @file_get_contents('https://www.viathinksoft.de/~daniel-marschall/oid-repository/ping_oid.php?oid='.$oid);
		$v = substr($v, 0, 1);
		if (trim($v) === '2') return true; // existing and approved
		if (trim($v) === '1') return false; // not approved
		if (trim($v) === '0') return false; // not existing

		# Fallback
		$url = self::getURL($oid);
		$responsecode = get_http_response_code($url);
		return ($responsecode == 200);
	}

	public static function processOID($oid, &$out, &$oidplusobj) {
		if (self::oidAvailable($oid)) {
			$url = self::getURL($oid);
			$out[] = "cross-ref:$url";
		}
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new OIDInfoFieldExtender());

# ---

# TODO: -> functions.inc.php
function get_http_response_code($theURL) {
	# http://php.net/manual/de/function.get-headers.php#97684
	$headers = get_headers($theURL);
	return substr($headers[0], 9, 3);
}
