<?php

# if (!interface_exists('VolcanoAuthProvider')) throw new Exception('Required interface "VolcanoAuthProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoAuthProvider.class.php';

class VolcanoAuthSHA1 implements VolcanoAuthProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'sha1', true); // is always case insensitive
	}

	public static function checkAuth($candidate, $token) {
		return sha1($token) === $candidate; // TODO salt
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
VolcanoDB::registerAuthProvider(new VolcanoAuthSHA1());

# --- TEST

/*
$x = new VolcanoAuthSHA1();
assert( $x->checkAuth('da39a3ee5e6b4b0d3255bfef95601890afd80709', ''));
assert( $x->checkAuth('a94a8fe5ccb19ba61c4c0873d391e987982fbbd3', 'test'));
assert(!$x->checkAuth('a295e0bdde1938d1fbfd343e5a3e569e868e1465', 'xyz'));
*/

