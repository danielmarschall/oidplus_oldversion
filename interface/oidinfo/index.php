<?php

include_once __DIR__ . '/../../includes/config.inc.php';

if (!OIDINFO_EXPORT_ENABLED) {
	die('Export disabled via switch OIDINFO_EXPORT_ENABLED.');
}

header("Content-type: text/xml");

set_time_limit(0);

include_once __DIR__ . '/../../includes/oid_plus.inc.php';
include_once __DIR__ . '/../../includes/oid_utils.inc.php';
include_once __DIR__ . '/../../includes/gui.inc.php';
include_once __DIR__ . '/../../includes/oidinfo_api.inc.php';

# TODO: mehr konfigurierbar...
# TODO ra(1)-person etc.... ra(2)  --> regex ?
# URL of OID+ system
# Also add non-desription-fields, e.g. "whois" @freeoid
# exclude GenRoot (aber was ist mit UUID definitions?)
# Check if OID is available at oidinfo
# nach dem rausfiltern soll make_tabs erneut aufgerufen werden...

# FreeOID: versuchen, vor und nachname aufzusplitten?

$db = new OIDPlus(__DIR__ . '/../../db/local.conf', true);
$db->addDir(__DIR__ . '/../../db');
$x = $db->listAllOIDs('.');

$oa = new OIDInfoAPI();

if (defined('OIDINFO_EXPORT_SIMPLEPINGPROVIDER')) {
	$oa->addSimplePingProvider(OIDINFO_EXPORT_SIMPLEPINGPROVIDER);
}


echo $oa->xmlAddHeader(OIDINFO_EXPORT_SUBMITTER_FIRST_NAME,
                       OIDINFO_EXPORT_SUBMITTER_LAST_NAME,
                       OIDINFO_EXPORT_SUBMITTER_EMAIL);

$params['allow_html'] = true; // TODO: allow_whitespaces !
$params['allow_illegal_email'] = true;
$params['soft_correct_behavior'] = OIDInfoAPI::SOFT_CORRECT_BEHAVIOR_NONE;
$params['do_online_check'] = false; // true;
$params['do_illegality_check'] = true;
$params['do_csv_check'] = true;
$params['auto_extract_name'] = '';
$params['auto_extract_url'] = '';
$params['always_output_comment'] = false;
$params['creation_allowed_check'] = defined('OIDINFO_EXPORT_SIMPLEPINGPROVIDER');

foreach ($x as $oid) {
	$filtered = array();

	$std_oid = substr($oid, 1);

	if (!$db->oidDescribed($oid)) continue;

	$cont = $db->showSingleOID($oid, '', OIDPlus::SEG_OIDDATA, true /*false*/);
	$cont = $db->filterRedactedEntries($cont);

	$ra_person_name = _filter($cont, 'ra(1)-person-name', false, false);
	$ra_org_name = _filter($cont, 'ra(1)-name', false, false);
	$pre_addr = '';
	if ($ra_person_name != '') {
		$xry = explode(' ', $ra_person_name, 2);
		$prename = $xry[0];
		$famname = $xry[1];
		$pre_addr = $ra_org_name;
	} else {
		$prename = $ra_org_name;
		$famname = '';
		$pre_addr  = '';
	}

/*
	$a_fto_curra_name = array();
	_add($a_fto_curra_name, _filter($cont, 'ra(1)-name', false, false));
	_add($a_fto_curra_name, _filter($cont, 'ra(1)-person-name', false, false));
	$fto_curra_name = implode(' / ', $a_fto_curra_name);
*/

	# naja... mit dem neuen code sieht das aus, als ob die VTS RA in MEINER adresse wäre...

	$a_fto_curra_address_p = array();
	_add($a_fto_curra_address_p, _filter($cont, 'ra(1)-person-org', false, false));
	_add($a_fto_curra_address_p, _filter($cont, 'ra(1)-person-organisation', false, false));
	_add($a_fto_curra_address_p, _filter($cont, 'ra(1)-person-address', false, false));
	_add($a_fto_curra_address_p, _country(_filter($cont, 'ra(1)-person-country', false, false)));
	$a_fto_curra_address_o = array();
	_add($a_fto_curra_address_o, _filter($cont, 'ra(1)-org', false, false));
	_add($a_fto_curra_address_o, _filter($cont, 'ra(1)-organisation', false, false));
	_add($a_fto_curra_address_o, _filter($cont, 'ra(1)-address', false, false));
	_add($a_fto_curra_address_o, _country(_filter($cont, 'ra(1)-country', false, false)));
	if (implode("\n", $a_fto_curra_address_o) != '') {
		$fto_curra_address = implode("\n", $a_fto_curra_address_o);
	} else {
		$fto_curra_address = implode("\n", $a_fto_curra_address_p);
	}
	if ($pre_addr != '') $fto_curra_address = $pre_addr."\n".$fto_curra_address;
	$fto_curra_address = _formatHTML($fto_curra_address, false);

	$fto_curra_email_p = _filter($cont, 'ra(1)-person-email', false, false);
	$fto_curra_email_o = _filter($cont, 'ra(1)-email', false, false);
	if ($fto_curra_email_o != '') {
		$fto_curra_email = $fto_curra_email_o;
	} else {
		$fto_curra_email = $fto_curra_email_p;
	}

	$fto_curra_phone_p = _filter($cont, 'ra(1)-person-phone', false, false);
	$fto_curra_phone_o = _filter($cont, 'ra(1)-phone', false, false);
	if ($fto_curra_phone_o != '') {
		$fto_curra_phone = $fto_curra_phone_o;
	} else {
		$fto_curra_phone = $fto_curra_phone_p;
	}

	$fto_curra_fax_p = _filter($cont, 'ra(1)-person-fax', false, false);
	$fto_curra_fax_o = _filter($cont, 'ra(1)-fax', false, false);
	if ($fto_curra_fax_o != '') {
		$fto_curra_fax = $fto_curra_fax_o;
	} else {
		$fto_curra_fax = $fto_curra_fax_p;
	}

	$att_changed = _filter($cont, 'modified', false, false);
	if ($att_changed == '') $att_changed = _filter($cont, 'changed', false, false);
	$att_changed = _findDate($att_changed);
	if ($att_changed == '0000-00-00') $att_changed = '';

	$att_created = _filter($cont, 'created', false, false);
	if ($att_created == '') $att_created = _filter($cont, 'assigned', false, false);
	if ($att_created == '') $att_created = _filter($cont, 'allocated', false, false);
	$att_created = _findDate($att_created);
	if ($att_created == '0000-00-00') $att_created = '';

	$att_asn1_id = explode("\n", trim(_filter($cont, 'identifier', false, false)));

	$att_description = _filter($cont, 'description', false, false);
	$att_information = _filter($cont, 'information', false, false) . "\n\n" . _filter($cont, 'comment', false, false);
	$att_name        = _filter($cont, 'name', false, false);
	if (!empty($att_name)) {
		$fto_desc = $att_name;
		$fto_info = $att_description."\n\n".$att_information;
	} else {
		$fto_desc = $att_description;
		$fto_info = $att_information;
	}

	$xry = explode('. ', $fto_desc, 2);
	if (count($xry) > 1) {
		$fto_desc = $xry[0];
		$fto_info = (($xry[1] == '') ? $xry[1]."\n\n" : '').$fto_info;
	}

	if (($fto_desc == '') && isset($att_asn1_id[0])) {
		$fto_desc = $att_asn1_id[0];
	}



	$rawdata = array();

	$ary = explode("\n", $cont);
	foreach ($ary as $a) {
		$bry = explode(":", $a);
		$b = trim($bry[0]);

		if (($b == 'attribute') && (isset($bry[1]))) {
			$val = strtoupper(trim($bry[1]));
			if ($val == 'DRAFT') continue 2; // do not export this OID at all
			if ($val == 'NOEXPORT') continue 2; // do not export this OID at all
		}

		if (isset($filtered[$b])) continue;
		if (substr($b, 0, 3) == 'ra(') continue;

		if ($b == 'index(uuid)') $a = 'UUID: '.trim($bry[1]);
		if ($b == 'index(doi)') $a = 'DOI: '.trim($bry[1]);
		if ($b == 'index(ipv4)') $a = 'IPv4: '.trim($bry[1]);
		if ($b == 'index(ipv6)') $a = 'IPv6: '.trim($bry[1]);
		if ($b == 'index(domain)') $a = 'Domain: '.trim($bry[1]);
		if ($b == 'unicodelabel') $a = 'Unicode label (IRI): '.trim($bry[1]);
		if ($b == 'attribute') $a = 'Attribute: '.ucfirst(trim($bry[1]));

		$a = ucfirst($a);

		$rawdata[] = $a;
	}
	$rawdata = implode("\n", $rawdata);

	$fto_info .= "\n\n$rawdata"; // TODO: test



	$fto_info = preg_replace('@^\n+@ism', '', $fto_info);
	$fto_desc = preg_replace('@^\n+@ism', '', $fto_desc);

	$fto_info = preg_replace('@\n+$@ism', '', $fto_info);
	$fto_desc = preg_replace('@\n+$@ism', '', $fto_desc);

	$fto_info = preg_replace('@\n{3,}$@ism', "\n\n", $fto_info);
	$fto_desc = preg_replace('@\n{3,}$@ism', "\n\n", $fto_desc);

	$use_monospace_desc = OIDINFO_EXPORT_SUBMITTER_ONLY_MONOSPACE;
	if (!$use_monospace_desc) {
		# Try to find evidence of ASCII art
		if (strpos($fto_desc, '----') !== false) {
			$use_monospace_desc = true;
		}
	}

	$use_monospace_info = OIDINFO_EXPORT_SUBMITTER_ONLY_MONOSPACE;
	if (!$use_monospace_info) {
		# Try to find evidence of ASCII art
		if (strpos($fto_info, '----') !== false) {
			$use_monospace_info = true;
		}
	}

	$fto_desc = _formatHTML($fto_desc, $use_monospace_desc);
	$fto_info = _formatHTML($fto_info, $use_monospace_info);

	# OID-Info does not accept <pre>.
	$fto_desc = str_replace('<pre>', '<code>', $fto_desc);
	$fto_info = str_replace('<pre>', '<code>', $fto_info);
	$fto_desc = str_replace('</pre>', '</code>', $fto_desc);
	$fto_info = str_replace('</pre>', '</code>', $fto_info);

	$elements = array();

	$elements['synonymous-identifier'] = $att_asn1_id;
	$elements['description'] = $fto_desc;
	$elements['information'] = $fto_info;

	$elements['first-registrant']['first-name'] = '';
	$elements['first-registrant']['last-name'] = '';
	$elements['first-registrant']['address'] = '';
	$elements['first-registrant']['email'] = '';
	$elements['first-registrant']['phone'] = '';
	$elements['first-registrant']['fax'] = '';
	$elements['first-registrant']['creation-date'] = '';
	if ($att_created != '') {
		if ($att_changed == '') { // We only save the current RA, because if the OID was changed, we do not know the old RA
			$elements['first-registrant']['first-name'] = $prename;
			$elements['first-registrant']['last-name'] = $famname;
			$elements['first-registrant']['address'] = $fto_curra_address;
			$elements['first-registrant']['email'] = $fto_curra_email;
			$elements['first-registrant']['phone'] = $fto_curra_phone;
			$elements['first-registrant']['fax'] = $fto_curra_fax;
		}
		$elements['first-registrant']['creation-date'] = $att_created;
	}

/*
	$elements['current-registrant']['first-name'] = '';
	$elements['current-registrant']['last-name'] = '';
	$elements['current-registrant']['address'] = '';
	$elements['current-registrant']['email'] = '';
	$elements['current-registrant']['phone'] = '';
	$elements['current-registrant']['fax'] = '';
	$elements['current-registrant']['modification-date'] = '';
	if ($att_changed != '') {
		$elements['current-registrant']['first-name'] = $prename;
		$elements['current-registrant']['last-name'] = $famname;
		$elements['current-registrant']['address'] = $fto_curra_address;
		$elements['current-registrant']['email'] = $fto_curra_email; // TODO: wieso gibt es da ein "<br />\n" , das dann zu "<br /><br />" wird ?
		$elements['current-registrant']['phone'] = $fto_curra_phone;
		$elements['current-registrant']['fax'] = $fto_curra_fax;
		$elements['current-registrant']['modification-date'] = $att_changed;
	}
*/
	$elements['current-registrant']['first-name'] = $prename;
	$elements['current-registrant']['last-name'] = $famname;
	$elements['current-registrant']['address'] = $fto_curra_address;
	$elements['current-registrant']['email'] = $fto_curra_email; // TODO: wieso gibt es da ein "<br />\n" , das dann zu "<br /><br />" wird ?
	$elements['current-registrant']['phone'] = $fto_curra_phone;
	$elements['current-registrant']['fax'] = $fto_curra_fax;
	if ($att_changed != '') {
		$elements['current-registrant']['modification-date'] = $att_changed;
	}

	echo $oa->createXMLEntry($std_oid, $elements, $params);
}

echo $oa->xmlAddFooter();

# ---

function _formatHTML($cont, $monospace) {
	global $db;
	return showHTML(trim($cont), $db, false, $monospace);
}

function _filter($cont, $name, $preg=false, $including_name=true) {
	global $filtered;
	$filtered[$name] = true;

	global $db;
	$res = $db->filterOutput($cont, $name, $preg, $including_name);
	return trim($res);
}

function _add(&$a, $c) {
	if ($c != '') $a[] = $c;
}

function _findDate($x) {
	$ary = explode(' ', $x);
	foreach ($ary as $v) {
		if (preg_match('@\\d{4}-\\d{2}-\\d{2}@', $v)) return $v;
	}
	return false;
}

function _country($country) {
	return Locale::getDisplayRegion('-'.$country, 'en');
}

