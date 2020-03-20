<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';
require_once __DIR__ . '/../../includes/oid_utils.inc.php';
require_once __DIR__ . '/../../includes/uuid_utils.inc.php';

class IRISearchFieldExtender implements OIDPlusFieldExtenders {
	private static function detectDirectIRINotation($x, &$xx, $oidplusobj) {
		# At your root, you can define your own iri-notation
		# FUT: as alternative, we could have "invisible" OIDs (cannot be queried) which give information about non-root OIDs
		$iripath = $oidplusobj->getValuesOf($x, 'iri-notation', true);
		if (count($iripath) > 0) {
			$oidplusobj->stripAttribs($x, 'iri-notation');

			$iripath = trim($iripath[0]);
			if (!iri_valid($iripath)) {
				throw new VolcanoException("IRI notation '$iripath' is invalid"); # TODO: source?
			}

			$iripath = trim($iripath);
			assert(substr($iripath, 0, 1) == '/');
			$iripath = substr($iripath, 1);

			$xx[] = $iripath;
			return true;
		}

		return false;
	}

	public static function processOID($oid, &$out, &$oidplusobj) {
		$xx = array();

		$x = $oid;
		do {
			$toparc = oid_toparc($x);

			if (self::detectDirectIRINotation($x, $xx, $oidplusobj)) break;

			$ids = $oidplusobj->getUnicodeLabels($x);

			$id = null;
			foreach ($ids as $m) {
				if (iri_arc_valid($m)) {
					$id = $m;
					break;
				}
			}

			if (is_null($id)) {
				$xx[] = $toparc;
			} else {
				$xx[]  = $id;
			}
		} while (($x = oid_up($x)) != '.');

		$xx = array_reverse($xx);

		if (count($xx) > 0) {
			$iripath = '/'.implode('/', $xx);

			if (!iri_valid($iripath)) {
				throw new VolcanoException("IRI notation '$iripath' is invalid"); # TODO: source?
			}

			# TODO: soll das auch für die existierenden "iri-notation" felder angewandt werden?
			$iripath = iri_add_longarcs($iripath);

			$out[] = "iri-notation: $iripath";
		}
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new IRISearchFieldExtender());
