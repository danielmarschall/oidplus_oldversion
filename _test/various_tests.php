<?php

include_once __DIR__ . '/../includes/oid_plus.inc.php';

# ------------------------------------------

header('Content-Type:text/plain');

$db = new OIDPlus();

$db->addDir(__DIR__ . '/../db');

/*

#echo "--------- Resolving identifiers\n";
#var_dump($db->resolveIdentifiers('.2.999.ax.ax.1.2.3'));

#echo "--------- Debug \n";
#$db->debug();

#echo "--------- Roots \n";
#$r = $db->findRoots();
#print_r($r);

#echo "--------- Find hello (str) \n";
#var_dump($db->findOID('hello'));

#echo "--------- Children .2.999 \n";
#print_r($db->listChildren('.2.999'));

#echo "--------- Children . \n";
#print_r($db->listChildren('.'));

#echo "--------- Rec show . \n";
#$db->rec_show('.');

# echo "--------- Rec show .2.999 \n";
# $db->rec_show('.2.999', 1);

echo "--------- Help \n";
$db->query('help');


echo "--------- List \n";
$db->query('oidplus:!list #7,8888,3');

echo "--------- Domain \n";
$db->query('oidplus:viathinksoft.de');

echo "--------- IP\n";
$db->query('oidplus:2001:1af8:4100:a061:0001::1336');
echo "--------- IP\n";
$db->query('oidplus:2001:1af8:4100:a061:0001::1337/127');
echo "--------- IP\n";
$db->query('oidplus:2001:1af8:4100:a061:0001::1337');

#echo "--------- Misc \n";
#$db->query('oidplus: hello world #0123 ');
#$db->query('oidplus: hello world ');


echo "--------- WELCOME\n";
$db->query('oidplus:!help');
$db->query('oidplus:!list');
$db->query('oidplus:!listIndexes');
$db->query('oidplus:.2.999.1');

*/

$db->query('oidplus:.1.3.6.1.4.1.37476.30.1.1.1.1.1812847950.1');

?>
