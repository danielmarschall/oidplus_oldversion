<?php

# TODO: das kann ohne suffix auskommen: http://dx.doi.org/10.1038/

# if (!interface_exists('VolcanoSearchProvider')) throw new Exception('Required interface "VolcanoSearchProvider" not found.');
# if (!class_exists('VolcanoDB')) throw new Exception('Required class "VolcanoDB" not found.');

require_once __DIR__ . '/../../core/VolcanoSearchProvider.class.php';

class VolcanoSearchDOI implements VolcanoSearchProvider {
	public static function checkId($id) {
		return self::strEqual($id, 'doi', true); // is always case insensitive
	}

	public static function calcDistance($candidate, $searchterm) {
		$y = explode('/', $searchterm);
		$x = explode('/', $candidate);
		$dist = count($y)-count($x);

		for ($i=0; $i<min(count($x),count($y)); $i++) {
			if ($x[$i] != $y[$i]) return false;
		}

		return $dist;
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
VolcanoDB::registerSearchProvider(new VolcanoSearchDOI());

# --- TEST

$x = new VolcanoSearchDOI();

assert($x->calcDistance('10.1000',  '10.1000/1') ==  1);
assert($x->calcDistance('10.1000/1',  '10.1000') ==  -1);
assert($x->calcDistance('10.1000',  '10.1000') ==  0);
assert($x->calcDistance('10.1001',  '10.1000') ===  false);

assert($x->calcDistance('10.1000/1/2/3',  '10.1000/1/2/3/4/5') ==  2);
assert($x->calcDistance('10.1000/1/2/3',  '10.1000/1/2/3') ==  0);
assert($x->calcDistance('10.1000/1/2/3/4/5',  '10.1000/1/2/3') ==  -2);

assert($x->calcDistance('10.1000/1/2/x',  '10.1000/1/2/3/4/5') ===  false);
assert($x->calcDistance('10.1000/1/2/x',  '10.1000/1/2/3') ===  false);
assert($x->calcDistance('10.1000/1/2/x/4/5',  '10.1000/1/2/3') ===  false);
