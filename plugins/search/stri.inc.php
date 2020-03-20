<?php

# if (!interface_exists('VolcanoSearchProvider')) throw new Exception('Required interface "VolcanoSearchProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoSearchProvider.class.php';

class VolcanoSearchStrI implements VolcanoSearchProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'stri', true); // is always case insensitive
	}

	public static function calcDistance($candidate, $searchterm) {
		return (self::strEqual($candidate, $searchterm, true)) ? 0 : -1;
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
VolcanoDB::registerSearchProvider(new VolcanoSearchStrI());

# --- TEST

/*
$x = new VolcanoSearchStrI();
assert($x->calcDistance('a', 'a') ==  0);
assert($x->calcDistance('a', 'A') ==  0);
assert($x->calcDistance('a', 'b') == -1);
*/

