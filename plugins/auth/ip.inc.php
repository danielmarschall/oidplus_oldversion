<?php

# if (!interface_exists('VolcanoAuthProvider')) throw new Exception('Required interface "VolcanoAuthProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../includes/ip_function.inc.php';
require_once __DIR__ . '/../../includes/ipv4_functions.inc.php';
require_once __DIR__ . '/../../includes/ipv6_functions.inc.php';

require_once __DIR__ . '/../../core/VolcanoAuthProvider.class.php';

class VolcanoAuthIP implements VolcanoAuthProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'ip', true); // is always case insensitive
	}

	public static function checkAuth($candidate, $token) {
		$ip = get_real_ip();
		if (strpos($candidate, ':') !== false) {
			return ipv6_in_cidr($candidate, $ip);
		} else {
			return ipv4_in_cidr($candidate, $ip);
		}
	}

	protected static function strEqual($str1, $str2, $caseInsensitive = false) {
		if ($caseInsensitive) {
			return strtolower($str1) == strtolower($str2);
		} else {
			return $str1 == $str2;
		}
	}
}

require_once __DIR__ . '/../../core/1_VolcanoDB.class.php';
VolcanoDB::registerAuthProvider(new VolcanoAuthIP());

# --- TEST

/*
$x = new VolcanoAuthIP();
assert( $x->checkAuth('217/8', ''));
assert( $x->checkAuth('::1/128', ''));
*/
