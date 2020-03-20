<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';

class OIDDepthFieldExtender implements OIDPlusFieldExtenders {
	public static function processOID($oid, &$out, &$oidplusobj) {
		$out[] = 'oid-depth:'.self::oid_depth($oid);
	}

	protected static function oid_depth($oid) {
		# TODO: in oid_utils hinzufügen
		if ($oid == '.') return 0;
		if ($oid[0] != '.') $oid = ".$oid";
		return substr_count($oid, '.');
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new OIDDepthFieldExtender());
