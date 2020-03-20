<?php

# Algorithm as specified by .1.3.6.1.4.1.37476.3.2.3.1

define('BASE64_CHARS', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=');

function identifierToNumber($identifier) {
	if ($identifier == '') return '99';
	$out = '';
	$identifier = utf8_encode($identifier);
	$identifier = base64_encode($identifier);
	for ($i=0; $i<strlen($identifier); $i++) {
		$p = $identifier[$i];
		$p = strpos(BASE64_CHARS, $p);
		$p = str_pad($p, 2, '0', STR_PAD_LEFT);
		$out .= $p;
	}
	return $out;
}

function numberToIdentifier($number) {
	if ($number == '99') return '';
	if (strlen($number)%2 != 0) return false;
	$out = '';
	for ($i=0; $i<=strlen($number); $i+=2) {
		$n = substr($number, $i, 2);
		$out .= substr(BASE64_CHARS, $n, 1);
	}
	$out = base64_decode($out);
	$out = utf8_decode($out);
	return $out;
}

function javaPackageNameToOID($identifier) {
	$out = '2.999';
	$ary = explode('.', $identifier);
	foreach ($ary as $a) {
		$out .= '.'.identifierToNumber($a);
	}
	return $out;
}

function OIDToJavaPackageName($oid) {
	$oid = preg_replace('@^2\\.999\\.@', '', $oid, -1, $c);
	if ($c == 0) return false;
	$out = array();
	$ary = explode('.', $oid);
	foreach ($ary as $a) {
		$out[] = numberToIdentifier($a);
	}
	return implode('.', $out);
}

$x = javaPackageNameToOID('test.de.viathinksoft.java.example...');

echo OIDToJavaPackageName($x);

?>
