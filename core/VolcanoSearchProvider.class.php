<?php

interface VolcanoSearchProvider {
	static function checkId($id);
	static function calcDistance($candidate, $searchterm);
}
