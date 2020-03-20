<?php

$roots = array();
#$roots[] = '.2.25.123';
#$roots[] = '.2.25.456';
$roots[] = '.1.3.6.1.4';
$roots[] = '.1.3.6.1.4.1.1234';
$roots[] = '.1.3.6.1.4.1.5678.2';

$hiarc = -1;

foreach ($roots as $r) {
	$c = explode('.', $r);
	$z = count($c);
	if ($z > $hiarc) $hiarc = $z;
}

echo "Hiarc: $hiarc\n";

$cr = '';
for ($i=1; $i<$hiarc; $i++) {
	$eq = null;
	$diff = false;
echo "--- $i ---\n";
	foreach ($roots as $r) {
		$c = explode('.', $r);
		$t = $c[$i];
		if (is_null($eq)) {
			$eq = $t;
		} else {
			if ($eq != $t) {
echo "Chk: $eq != $t\n";

				$diff = true;
				break;
			}
		}
	}
	if ($diff) {
		$c = explode('.', $roots[0]);
		$o = array();
		for ($j=$i-2; $j>=0; $j--) {
			$o[] = $c[$j];
		}
		$o = array_reverse($o);
		$cr = implode('.', $o);
#		if ($cr == '') $cr = '.';
		echo "Common root: ".$cr."\n";;
		break;
	}
}

$zzz = array();
$zzz[$cr] = true;
foreach ($roots as $r) {
	echo "Proc $r ($cr)\n";
	$r = substr($r, strlen($cr));
#	$r = substr($r, 1).'.';
#	$r = $cr.'.'.substr($r, 0, strpos($r, '.'));
	$v = explode('.', $r);
	array_pop($v);

print_r($v);

	$a = $cr;
	foreach ($v as $vv) {
		$a .= 'x'.$vv;
		$zzz[$a] = true;

	}
	echo "\n";
}

foreach ($zzz as $z => $x) {
	echo "X = $z\n";
}

?>
