<?php

# if (!interface_exists('VolcanoSearchProvider')) throw new Exception('Required interface "VolcanoSearchProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoSearchProvider.class.php';

class VolcanoSearchDomain implements VolcanoSearchProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'domain', true); // is always case insensitive
	}

	protected static function tryNormalize($term) {
		// TODO: also validate syntax
		$ary = explode(':', $term);
		$term = $ary[0];
		if (substr($term, -1, 1) != '.') $term .= '.';
		return $term;
	}

	public static function calcDistance($candidate, $searchterm) {
		// TODO: punycode?

		$candidate  = self::tryNormalize($candidate);
		$searchterm = self::tryNormalize($searchterm);

		if (strlen($candidate) <= strlen($searchterm)) {
			$cmp = substr($searchterm, strlen($searchterm)-strlen($candidate));
			if (!self::strEqual($cmp, $candidate, true)) return false;

			$subdoms = substr($searchterm, 0, strlen($searchterm)-strlen($candidate));
			return substr_count($subdoms, '.');
		} else {
			$cmp = substr($candidate, strlen($candidate)-strlen($searchterm));
			if (!self::strEqual($cmp, $searchterm, true)) return false;

			$too_specific = substr($candidate, 0, strlen($candidate)-strlen($searchterm));
			return -substr_count($too_specific, '.');
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
VolcanoDB::registerSearchProvider(new VolcanoSearchDomain());

# --- TEST

/*
$x = new VolcanoSearchDomain();
assert($x->calcDistance('de.',                  'viathinksoft.DE.') ==  1);
assert($x->calcDistance('de.',                  'viathinksoft.de.') ==  1);
assert($x->calcDistance('viathinksoft.de.',     'viathinksoft.de.') ==  0);
assert($x->calcDistance('viathinksoft.DE.',     'viathinksoft.de.') ==  0);
assert($x->calcDistance('www.viathinksoft.de.', 'viathinksoft.de.') == -1);

assert($x->calcDistance('de.',                  'viathinksoft.xx.') === false);
assert($x->calcDistance('viathinksoft.de.',     'viathinksoft.xx.') === false);
assert($x->calcDistance('www.viathinksoft.de.', 'viathinksoft.xx.') === false);
*/
