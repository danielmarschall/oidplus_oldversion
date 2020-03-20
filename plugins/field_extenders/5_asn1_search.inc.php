<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';
require_once __DIR__ . '/../../includes/uuid_utils.inc.php';

class ASN1SearchFieldExtender implements OIDPlusFieldExtenders {
	private static function detectDirectAsn1Notation($x, &$xx, $oidplusobj) {
		# At your root, you can define your own asn1-notation
		# FUT: as alternative, we could have "invisible" OIDs (cannot be queried) which give information about non-root OIDs
		$asn1path = $oidplusobj->getValuesOf($x, 'asn1-notation', true);
		if (count($asn1path) > 0) {
			$oidplusobj->stripAttribs($x, 'asn1-notation');

			$asn1path = trim($asn1path[0]);
			if (!asn1_path_valid($asn1path)) {
				throw new VolcanoException("ASN.1 notation '$asn1path' is invalid"); # TODO: source?
			}
			$asn1path = preg_replace('@^\\s*\\{(.+)\\}\\s*$@', '\\1', $asn1path);

			$asn1path_neu = '';
			$asn1path = str_replace("\t", ' ', $asn1path);
			$z = explode(' ', $asn1path);
			foreach ($z as $za) {
				$za = trim($za);
				if ($za == '') continue;
				$asn1path_neu .= "$za ";
			}
			$asn1path_neu = trim($asn1path_neu);

			$xx[] = $asn1path_neu;
			return true;
		}

		return false;
	}

	public static function processOID($oid, &$out, &$oidplusobj) {
		$xx = array();

		$x = $oid;
		do {
			$toparc = oid_toparc($x);

			if (self::detectDirectAsn1Notation($x, $xx, $oidplusobj)) break;

			$ids = $oidplusobj->getIdentifiers($x);

			$id = null;
			foreach ($ids as $m) {
				if (oid_id_is_valid($m)) {
					$id = $m;
					break;
				}
			}

			if (is_null($id)) {
				$xx[] = $toparc;
			} else {
				$xx[]  = "$id($toparc)";
			}
		} while (($x = oid_up($x)) != '.');

		$xx = array_reverse($xx);

		if (count($xx) > 0) {
			$asn1path = '{ '.implode(' ', $xx).' }';

			if (!asn1_path_valid($asn1path)) {
				throw new VolcanoException("ASN.1 notation '$asn1path' is invalid"); # TODO: source?
			}

			$out[] = "asn1-notation: $asn1path";
		}
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new ASN1SearchFieldExtender());
