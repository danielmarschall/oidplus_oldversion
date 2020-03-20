<?php

# if (!interface_exists('VolcanoSearchProvider')) throw new Exception('Required interface "VolcanoSearchProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoSearchProvider.class.php';
require_once __DIR__ . '/../../includes/ipv6_functions.inc.php';

class VolcanoSearchIPv6 implements VolcanoSearchProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'ipv6', true); // is always case insensitive
	}

	public static function calcDistance($candidate, $searchterm) {
		return ipv6_distance($searchterm, $candidate); // TODO: richtig rum?
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
VolcanoDB::registerSearchProvider(new VolcanoSearchIPv6());

# --- TEST

/*
$x = new VolcanoSearchIPv6();
assert($x->calcDistance('2001:1ae0::/27', '2001:1af8::/29') ==  2);
assert($x->calcDistance('2001:1af8::/29', '2001:1af8::/29') ==  0);
assert($x->calcDistance('2001:1af8::/31', '2001:1af8::/29') == -2);

assert($x->calcDistance('2002:1af8:4100:a061:0001::1335/127', '2001:1af8:4100:a061:0001::1336/127') === false);
assert($x->calcDistance('2001:1af8:4100:a061:0001::1337/128', '2001:1af8:4100:a061:0001::1336/128') === false);
assert($x->calcDistance('2001:1af8:4100:a061:0001::1337',     '2001:1af8:4100:a061:0001::1336')     === false);
*/

