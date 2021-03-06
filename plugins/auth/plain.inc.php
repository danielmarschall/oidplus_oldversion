<?php

# if (!interface_exists('VolcanoAuthProvider')) throw new Exception('Required interface "VolcanoAuthProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoAuthProvider.class.php';

class VolcanoAuthPlain implements VolcanoAuthProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'plain', true); // is always case insensitive
	}

	public static function checkAuth($candidate, $token) {
		return $token === $candidate; // TODO case?
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
VolcanoDB::registerAuthProvider(new VolcanoAuthPlain());

# --- TEST

/*
$x = new VolcanoAuthPlain();
assert( $x->checkAuth('',     ''));
assert( $x->checkAuth('test', 'test'));
assert(!$x->checkAuth('beta', 'xyz'));
*/

