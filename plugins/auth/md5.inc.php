<?php

# if (!interface_exists('VolcanoAuthProvider')) throw new Exception('Required interface "VolcanoAuthProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoAuthProvider.class.php';

class VolcanoAuthMD5 implements VolcanoAuthProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'md5', true); // is always case insensitive
	}

	public static function checkAuth($candidate, $token) {
		return md5($token) === $candidate; // TODO salt
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
VolcanoDB::registerAuthProvider(new VolcanoAuthMD5());

# --- TEST

/*
$x = new VolcanoAuthMD5();
assert( $x->checkAuth('d41d8cd98f00b204e9800998ecf8427e', ''));
assert( $x->checkAuth('098f6bcd4621d373cade4e832627b4f6', 'test'));
assert(!$x->checkAuth('987bcab01b929eb2c07877b224215c92', 'xyz'));
*/

