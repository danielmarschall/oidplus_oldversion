<?php

# if (!interface_exists('VolcanoSearchProvider')) throw new Exception('Required interface "VolcanoSearchProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoSearchProvider.class.php';
require_once __DIR__ . '/../../includes/ipv4_functions.inc.php';

class VolcanoSearchIPv4 implements VolcanoSearchProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'ipv4', true); // is always case insensitive
	}

	public static function calcDistance($candidate, $searchterm) {
		return ipv4_distance($searchterm, $candidate); // TODO: richtig rum?
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
VolcanoDB::registerSearchProvider(new VolcanoSearchIPv4());

# --- TEST

/*
$x = new VolcanoSearchIPv4();

assert($x->calcDistance('192.168.0.0/16',  '192.168.64.0/18') ===  2);
assert($x->calcDistance('192.168.64.0/18', '192.168.64.0/18') ===  0);
assert($x->calcDistance('192.168.64.0/20', '192.168.64.0/18') === -2);

assert($x->calcDistance('192.168.69.200/31', '192.168.69.202/31') === false);
assert($x->calcDistance('192.168.69.200/32', '192.168.69.201/32') === false);
assert($x->calcDistance('192.168.69.200',    '192.168.69.201')    === false);

assert($x->calcDistance('95.211.38.42/32', '95.211.38.42') === 0);
assert($x->calcDistance('95.211.38.42', '95.211.38.42/32') === 0);
*/
