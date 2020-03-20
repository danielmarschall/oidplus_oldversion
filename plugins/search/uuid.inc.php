<?php

# if (!interface_exists('VolcanoSearchProvider')) throw new Exception('Required interface "VolcanoSearchProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoSearchProvider.class.php';

class VolcanoSearchUUID implements VolcanoSearchProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'guid', true) || self::strEqual($id, 'uuid', true); // is always case insensitive
	}

	protected static function tryNormalize($term) {
		// TODO: also validate syntax
		$term = str_replace('{', '', $term);
		$term = str_replace('}', '', $term);
		$term = strtolower($term);
		return $term;
	}

	public static function calcDistance($candidate, $searchterm) {
		$candidate  = self::tryNormalize($candidate);
		$searchterm = self::tryNormalize($searchterm);

		if ($candidate == $searchterm) {
			return 0;
		} else {
			return false;
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
VolcanoDB::registerSearchProvider(new VolcanoSearchUUID());

# --- TEST

/*
$x = new VolcanoSearchUUID();

assert($x->calcDistance('fd3c657c-0728-4769-b608-db5ece442c97', '8338af1c-61ea-41c1-aded-c836846ae22d') === false);
assert($x->calcDistance('fd3c657c-0728-4769-b608-db5ece442c97', 'fd3c657c-0728-4769-b608-db5ece442c97') === 0);
assert($x->calcDistance('fd3c657c-0728-4769-b608-db5ece442c97', '{FD3c657c-0728-4769-b608-db5ece442C97}') === 0);
assert($x->calcDistance('{fd3C657C-0728-4769-b608-db5ece442c97}', 'fd3c657c-0728-4769-b608-db5ece442c97') === 0);
*/

