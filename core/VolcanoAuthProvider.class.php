<?php

interface VolcanoAuthProvider {
	static function checkId($id);
	static function checkAuth($candidate, $token);

}
