<?php

# if (!interface_exists('OIDPlusFieldExtenders')) throw new Exception('Required interface "OIDPlusFieldExtenders" not found.');
# if (!class_exists('OIDPlus')) throw new Exception('Required class "OIDPlus" not found.');

require_once __DIR__ . '/../../core/OIDPlusFieldExtenders.class.php';
require_once __DIR__ . '/../../includes/uuid_utils.inc.php';

class UUIDFieldExtender implements OIDPlusFieldExtenders {
	public static function processOID($oid, &$out, &$oidplusobj) {
		$r = oid_to_uuid($oid);

		if ($r === false) {
			$uuid_level = 0;
		} else {
			if (oid_depth($oid) == 2) {
				$uuid_level = 1;
			} else {
				$uuid_level = 2;
			}
		}

		# TODO: more configuration values, e.g. to hide namebased UUIDs

		if (($uuid_level == 0) || ($uuid_level == 2) || ($oidplusobj->getConfigValue('namebased_uuids_for_pure_uuids') == '1')) {
			$out[] = 'namebased-uuid-sha1:'.gen_uuid_sha1_namebased(UUID_NAMEBASED_NS_OID, $oid);
			$out[] = 'namebased-uuid-md5:'.gen_uuid_md5_namebased(UUID_NAMEBASED_NS_OID, $oid);
		}
		if ($uuid_level == 1) {
			$out[] = 'uuid:'.$r;
		}
		if ($uuid_level == 2) {
			$out[] = 'origin-uuid:'.$r;
		}
	}
}

require_once __DIR__ . '/../../core/2_OIDPlus.class.php';
OIDPlus::registerFieldExtender(new UUIDFieldExtender());
