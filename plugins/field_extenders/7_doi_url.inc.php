<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';
require_once __DIR__ . '/../../includes/uuid_utils.inc.php';

class DOILinkFieldExtender implements OIDPlusFieldExtenders {

	public static function findDOI($oid, &$oidplusobj) {
		$indexes = $oidplusobj->getDatasets($oid, 'index');

		$found_doi = null;
		foreach ($indexes as $index) {
			$params = $index['attrib_params'];
			$is_doi = false;
			foreach ($params as $param) {
				if (strtolower(trim($param)) == 'doi') {
					$is_doi = true;
					break;
				}
			}

			if ($is_doi) {
				$found_doi = trim($index['value']);
				break;
			}
		}

		return $found_doi;
	}

	public static function processOID($oid, &$out, &$oidplusobj) {
		$doi = self::findDOI($oid, $oidplusobj);
		if (!is_null($doi)) {
			$out[] = "doi-resolve:http://dx.doi.org/$doi";
		}
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new DOILinkFieldExtender());
