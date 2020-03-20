<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';
require_once __DIR__ . '/../../includes/OidDerConverter.class.phps';

class DEREncodingFieldExtender implements OIDPlusFieldExtenders {
	public static function processOID($oid, &$out, &$oidplusobj) {
		$out[] = 'der-encoding:'.OidDerConverter::hexarrayToStr(OidDerConverter::oidToDER($oid));
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new DEREncodingFieldExtender());
