<?php

error_reporting(E_ALL | E_NOTICE | E_DEPRECATED | E_STRICT);

// Note: There may not be a function include_all(), otherwise, global variables (e.g. for caching) cannot be used in the included units.

// Load Core Stuff
foreach (sorted_glob(__DIR__ . '/../core/*.php') as $filename) include_once $filename;

// Load Search Providers
// Load Authentification Providers
// Load Field Extenders
foreach (sorted_glob(__DIR__ . '/../plugins/*/*.php') as $filename) include_once $filename;

# ---

function sorted_glob($mask) {
	$files = glob($mask);
	sort($files);
	return $files;
}
