<?php

# TODO:
#	<oid1>
#		...
#			<orphan>

// TODO: {...} = ein link zu einer whois query

// TODO !list auch ohne oidplus:

# todo: auch "oid:" erlauben (ohne beginning dot)

# TODO is_numeric() nicht verwenden bei oidutils... stattdessen [0-9]+

// TODO: suchen nach eigenschaften, z.b. created:2012-...
# todo: exceptions im frontent oder false results?
# todo: was ist private, was protected?
# todo: ismU ohne m etc
# todo: headless camel notation
# todo: distance funktion: (a,b) und (b,a) syntax nicht eindeutig... mal so mal so. (plugins/*.php und ipv?_functions.php)

# todo: extends ok oder ist es keine oop-erweiterung?
require_once __DIR__ . '/1_VolcanoDB.class.php';
class OIDPlus extends VolcanoDB {
	protected function displayLine($oid) {
		$described   = $this->oidDescribed($oid);
		$identifiers = $this->getIdentifiers($oid);
		$oid_attribs = $this->getOIDAttribs($oid);

		$out = $oid;

		$oidary = explode('.', $oid);
		$lastarc = $oidary[count($oidary)-1];
		if (count($identifiers) == 0) {
			$out .= " $lastarc";
		} else {
			foreach ($identifiers as $n => $i) {
				if ($n != 0) $out .= " |";
				$out .= " $i($lastarc)";
			}
		}
		if (count($oid_attribs) > 0) {
			$out .= " [".implode(',',$oid_attribs)."]";
		}
		if (!$described) $out .= " [not described]";
		# TODO: '...' anzeigen zwischen einer OID und einer orphan OID?
		return $out;
	}

	protected function rec_show($oid, &$out, $desc='', $levels=-1) {
		$x = $this->listAllOIDs($oid, $levels);
		foreach ($x as $oid) {
			$out .= $desc . $this->displayLine($oid) . "\n";
		}
		unset($x);
	}

	protected function rec_listAllOIDs($oid, &$out, $levels=-1) {
		$children = $this->listChildren($oid, $levels);
		if ($children === false) {
			#echo "...\n"; # TODO problem, es ist ungewiss ob noch was untergeordnet ist!
			return;
		}

		foreach ($children as $num => &$data) {
			$dotstop = self::appendDot($oid);
			$cur_oid = $dotstop.$num;
			$out[] = $cur_oid;
			$this->rec_listAllOIDs($cur_oid, $out, $levels-1);
		}
		unset($data);
	}

	public function listAllOIDs($oid, $levels=-1) {
		$out = array();
		$this->rec_listAllOIDs($oid, $out, $levels);
		return $out;
	}

/* TODO: das wäre einfacherer, aber das geht nicht mit orphan OIDS !!!
public function listAllOIDs($parent_oid, $depth=-1, $check_auth=true) {
return $this->listOIDs($parent_oid, true, $depth=-1, $check_auth=true);
}
*/

	public function count_roots() {
		return count($this->findRoots());
	}

	public function count_oids() {
		$oids = $this->getAllOIDs();
		return count($oids);
	}

	public function count_indexes() {
		$cnt = 0;
		$oids = $this->getAllOIDs();
		foreach ($oids as &$oid) {
			$arys = $this->getDatasets($oid, 'index');
			if (count($arys) > 0) $cnt++;
		}
		return $cnt;
	}

	protected function sc_listIndexes() {
		$out = '';
		# TODO: + oid anzeigen?
		$oids = $this->getAllOIDs();
		foreach ($oids as &$oid) {
			$arys = $this->getDatasets($oid, 'index');
			foreach ($arys as &$ary) {
				if (!isset($ary['attrib_params'][0])) {
					throw new VolcanoException('index() field without any param', $ary);
				}
				$nid = $ary['attrib_params'][0];
				$val = $ary['value'];
				# TODO: domain mit puny-decoding?
				$out .= "index($nid):$val\n";
			}
		}
		return $out;
	}

	protected function sc_listRoots() {
		$out = '';
		$roots = $this->findRoots();
		foreach ($roots as &$root) {
			$out .= "oid:" . $this->displayLine($root) . "\n"; # todo "oid:" mittels maketabs alignen
		}
		return $out;
	}

	protected function sc_list($show_complete_tree = false) {
		$out = '';
		if ($show_complete_tree) {
			$roots = array('.');
		} else {
			$roots = $this->findRoots();
		}
		$first = true;
		foreach ($roots as &$root) {
			// Auskommentiert: Abstand zwischen den OIDs von 2 Roots lassen
			// if (!$first) echo "\n";
			$first = false;
			$out .= "oid:" . $this->displayLine($root) . "\n";
			$this->rec_show($root, $out, "oid:");
		}

		$out2 = array();
		$max_len_oid = 0;
		$max_dep_oid = 0;
		$min_dep_oid = PHP_INT_MAX;
		$lines = explode("\n", trim($out));
		foreach ($lines as &$x) {
			$m = explode(' ', $x, 2);
			$oid = substr($m[0], 4);
			$len = strlen($oid);
			$dep = oid_len($oid);
			$entry = isset($m[1]) ? $m[1] : '';
			$out2[] = array($len, $dep, $oid, $entry);
			if ($len > $max_len_oid) $max_len_oid = $len;
			if ($dep > $max_dep_oid) $max_dep_oid = $dep;
			if ($dep < $min_dep_oid) $min_dep_oid = $dep;
		}

		$out = '';
		foreach ($out2 as &$data) {
			$len = $data[0];
			$dep = $data[1];
			$oid = $data[2];
			$entry = $data[3];

			if (empty($entry)) $entry = '<no identifier>';

			$oid = str_pad($oid, $max_len_oid);
			$entry = str_repeat('.    ', $dep-$min_dep_oid).$entry;

			$out .= "oid:$oid | $entry\n";
		}

		unset($root);
		return $out;
	}

	protected static function help() {
		// TODO syntax for searching
		echo "System commands:\n";
		echo "\thelp\n";
		echo "\toidplus:!list [#<authtoken>[,<authtoken>[,...]]]\n";
		echo "\toidplus:!listRoots [#<authtoken>[,<authtoken>[,...]]]\n";
		echo "\toidplus:!listIndexes [#<authtoken>[,<authtoken>[,...]]]\n";
# todo: listAll
		echo "\toidplus:!help\n";
		echo "\n";
		echo "Seaching for indexed items (index attribute):\n";
		echo "\toidplus:<index> [#<authtoken>[,<authtoken>[,...]]]\n";
		echo "\n";
		echo "Lookup a single OID:\n";
		echo "\toidplus:.2.999 [#<authtoken>[,<authtoken>[,...]]]\n";
		echo "\toidplus:.2.example [#<authtoken>[,<authtoken>[,...]]]\n";
	}

	# todo: kein echo sondern string return?
	public function query($q) {
		$q = trim($q);

#		echo "Process query '$q'\n\n";

		$this->clearAuthTokens();

		if ($q == 'help') {
			return self::help();
		}

		if (!preg_match('@^oidplus:([^#]+)(#([^#]*)){0,1}$@ismU', $q, $m)) {
			# return false;

			# TODO: eigentlich sollte die syntax nicht willkürlich sein
			# lieber "oid:(non-dotted-oid without authToken and no indexes)
		#	$q = preg_replace('@^oidplus:@isU', '', $q);
		#	$q = preg_replace('@^(urn:){0,1}oid:@isU', '.', $q);
			$q = preg_replace('@^(urn:){0,1}oid:@isU', '', $q);
			if (oid_valid_dotnotation($q, true, false) && ($q[0] != '.')) $q = ".$q";

			$q = "oidplus:$q";
			return $this->query($q);
		} else {
			$q    = trim($m[1]);
			$pins = (isset($m[3])) ? trim($m[3]) : null;

			if (oid_valid_dotnotation($q, true, false) && ($q[0] != '.')) $q = ".$q";
		}

		if (!is_null($pins)) {
			$pins = explode(',', $pins);
			foreach ($pins as &$pin) {
				$this->addAuthToken($pin);
			}
			unset($pin);
		}

		if ($q[0] == '!') {
			$syscommand = trim(substr($q, 1));
			if ($syscommand == 'help') {
				return self::help();
			} else if ($syscommand == 'list') {
				$out = self::sc_list();
				echo self::make_tabs($out);
				return;
			} else if ($syscommand == 'listRoots') {
				$out = self::sc_listRoots();
				echo self::make_tabs($out);
				return;
			} else if ($syscommand == 'listIndexes') {
				$out = self::sc_listIndexes();
				echo self::make_tabs($out);
				return;
			} else {
				// TODO error
				return false;
			}
		} else if ($q[0] == '.') {
			// Single OID
			// TODO auch ohne leading dot?

			echo self::make_tabs($this->showSingleOID($q));
			return;
		} else {
			// Indexed name (string, ipv4, ipv6, domain, doi, guid, ...)

			$prefix  = '';
			$ary     = $this->findOID($q);
			if ($ary) {
				$oid  = $ary[0];
				$dist = $ary[1];
				$nid  = $ary[2];

				if ($dist > 0) {
					$prefix .= "searchterm:$q\n";
					$prefix .= "search-result:Did not find an exact match for the given searchterm, but found a superior node\n";
					$prefix .= "distance($nid):$dist\n";
					$prefix .= "\n";
				}

				echo self::make_tabs($this->showSingleOID($oid, $prefix));
				return;
			} else {
				$prefix .= "searchterm:$q\n";
				# $prefix .= "search-result:Not found\n";
				$prefix .= "search-result:No exact match and no superior node was found\n";
				echo self::make_tabs($prefix);
			}
		}
	}

	public static function filterOutput($output, $name, $preg=false, $including_name=true) {
		$out = '';

		$lines = explode("\n", $output);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line == '') continue;
			$name_val = explode(':', $line, 2);
			if (count($name_val) != 2) continue;
			if ($preg) {
				$ok = preg_match($name, $name_val[0], $m);
			} else {
				$ok = $name == trim($name_val[0]);
			}
			if ($ok) {
				if ($including_name) {
					$out .= $line."\n";
				} else {
					$out .= $name_val[1]."\n";
				}
			}

		}

		return $out;
	}

	const SEG_OIDINFO = 2;
	const SEG_OIDDATA = 4;
	const SEG_IDX_BROWSER = 8;
	const SEG_OID_BROWSER = 16;
	/*protected*/ public function showSingleOID($oid, $prefix='', $segments = self::SEG_OIDINFO+self::SEG_OIDDATA+self::SEG_IDX_BROWSER+self::SEG_OID_BROWSER, $make_tabs=true) {
		if (!oid_valid_dotnotation($oid, true, substr($oid,0,1) == '.')) {
			throw new VolcanoException("OID '$oid' is invalid");
		}

		$out_segs = array();

		$oid = sanitizeOID($oid, substr($oid,0,1) == '.');
		if ($oid === false) return false;

		// --- Segment 1 ---

		if (($segments & self::SEG_OIDINFO) == self::SEG_OIDINFO) {
			$out = "oid:$oid\n"; # TODO: soll das "searchterm" heißen, wenn nur superior node gefunden wurde?

			# TODO: nach search-result: verschieben?
			foreach (self::$fieldExtenders as $fe) {
				$x = array();
				$fe->processOID($oid, $x, $this);
				if (count($x) > 0) {
					$out .= implode("\n", $x)."\n";
				}
			}

			if ($this->oidDescribed($oid)) {
				$out .= "search-result:Found\n";
			} else {
				# TODO: wenn der user eine ungültige oid eingibt, dann keinen "internal error" werfen
				$out .= "search-result:Not found\n";
			}

			$out_segs[] = $out;
		}

		// --- Segment 2 ---

		if (($segments & self::SEG_OIDDATA) == self::SEG_OIDDATA) {
			$out = '';
			$x = $this->getDatasets($oid);
			foreach ($x as &$y) {
				if ($this->isSpecialInvisibleField($y)) continue; // system fields, like '*read-auth' are invisible
				$out .= $y['attrib_name'];
				if (count($y['attrib_params']) > 0) $out .= '('.implode(',', $y['attrib_params']).')';
				$out .= ':'.$y['value']."\n"; # todo: +params? TEST.
			}

			$out_segs[] = $out;
		}

		# --- Segment 3: Index-Navigation ---

		if (($segments & self::SEG_IDX_BROWSER) == self::SEG_IDX_BROWSER) {
			$index_browser_ns = '';
			$index_browser_val = '';
			$index_browser_found = false;
			if (isset($this->oid_data[$oid])) {
				foreach ($this->oid_data[$oid] as $tmp) {
					if ($tmp['attrib_name'] == 'index') {
						if (!isset($tmp['attrib_params'][0])) continue; # TODO: exception?
						$index_browser_ns = $tmp['attrib_params'][0];

#						$index_browser_val = $tmp['value'];
						if (($tmp['flag_confidential']) && (!$this->isAuthentificated($oid))) {
							if (!$this->isRedactedMessageEnabled()) {
								continue; // hide complete line
							} else {
								$index_browser_val = $this->redactedMessage;
							}
						} else {
							$index_browser_val = $tmp['value'];
						}

						$index_browser_found = true;
						break;
					}
				}
			}

			if (($index_browser_found) && ($found_prov = self::findSearchProvider($index_browser_ns))) {
				$out = '';

				$nearest_father = array(false, PHP_INT_MAX, '');
				$subs = array();

				$index_ns_root = oid_up($oid);

				$cry = $this->listChildren($index_ns_root, 1);
				foreach ($cry as $num => $c) {
					$oid_2 = $index_ns_root.'.'.$num;

					$index_browser_ns_2 = '';
					$index_browser_val_2 = '';
					$index_browser_found_2 = false;
					foreach ($this->oid_data[$oid_2] as $tmp_2) {
						if ($tmp_2['attrib_name'] == 'index') {
							if (!isset($tmp_2['attrib_params'][0])) continue; # TODO: exception?
							$index_browser_ns_2 = $tmp_2['attrib_params'][0];

#							$index_browser_val_2 = $tmp_2['value'];
							if (($tmp_2['flag_confidential']) && (!$this->isAuthentificated($oid_2))) {
								if (!$this->isRedactedMessageEnabled()) {
									continue; // hide complete line
								} else {
									$index_browser_val_2 = $this->redactedMessage;
								}
							} else {
								$index_browser_val_2 = $tmp_2['value'];
							}

							$index_browser_found_2 = true;
							break;
						}
					}

					if ($index_browser_found_2) {
						$cur_distance = $found_prov->calcDistance($index_browser_val, $index_browser_val_2);

						if ($index_browser_val == $index_browser_val_2) continue;

						if ($cur_distance === false) continue;

						if ($cur_distance < 0) {
							if (abs($cur_distance) < $nearest_father[1]) {
								$nearest_father[0] = true;
								$nearest_father[1] = abs($cur_distance);
								$nearest_father[2] = array($index_browser_val_2);
							} else if (abs($cur_distance) == $nearest_father[1]) {
								$nearest_father[2][] = $index_browser_val_2;
							}
						} else if ($cur_distance > 0) {
							$subs[] = $index_browser_val_2;
						}
					}
				}

				# TODO FUT: brothers (selbe dist zum vater)

				// Nur die untergeordneten Knoten anzeigen, zwischen denen kein weiterer Knoten zu unserem Knoten besteht.
				// K  <-- a <-- a' <-- a''
				//    <-- b <-- b'
				//          <-- c
				// => Wähle a, b, c
				foreach ($subs as &$sub1) {
					foreach ($subs as &$sub2) {
						if ($sub1 == $sub2) continue;

						$dist = ($found_prov->calcDistance($sub1, $sub2));
						if ($dist === false) continue;
						if ($dist > 0) $sub2 = null;
					}
				}

				if ($nearest_father[0]) {
					foreach ($nearest_father[2] as $tmp) {
						$out .= "father-index($index_browser_ns):$tmp\n";
					}
				}
				$out .= "self-index($index_browser_ns):$index_browser_val\n";
				foreach ($subs as $tmp) {
					if (is_null($tmp)) continue;
					$out .= "son-index($index_browser_ns):$tmp\n";
				}

				$out_segs[] = $out;
			}
		}

		# --- Segment 4: OID Navigation ---

		if (($segments & self::SEG_OID_BROWSER) == self::SEG_OID_BROWSER) {
			$out = '';

			// Show father
			if ($oid != '.') {
				// $out .= "father:" . $this->displayLine(oid_up($oid)) . "\n";
				$i=0;
				$cur_oid = $oid;
				$father_out = '';

				$tmp_table = array();

				while ($cur_oid != '.') {
					$i++;
					$cur_oid = oid_up($cur_oid);
					$tmp_table[] = array($this->oidDescribed($cur_oid), "superior-node($i):" . $this->displayLine($cur_oid));
				}

				$described_once = false;
				for ($i=count($tmp_table)-1; $i>=0; $i--) {
					$t = $tmp_table[$i];

					$is_desc = $t[0];
					$line    = $t[1];

					if ($is_desc) $described_once  = true;

					if ($is_desc || $described_once ) {
						$father_out .= "$line\n";
					}
				}

				$out .= $father_out;
			}

			// Show current OID
			$out .= "current-node:" . $this->displayLine($oid) . "\n";

			// Show first level children (also undescribed ones)
			$x = $this->listChildren($oid, 1);
			foreach ($x as $num => &$data) {
				$dotstop = self::appendDot($oid);
				$cur_oid = $dotstop.$num;
				$out .= "child-node:" . $this->displayLine($cur_oid) . "\n";
			}

			$out_segs[] = $out;

			// Brothers

			$out = '';

			$x = $this->listChildren(oid_up($oid), 1);
			foreach ($x as $num => &$data) {
				$dotstop = self::appendDot(oid_up($oid));
				$cur_oid = $dotstop.$num;
				if ($cur_oid != $oid) $out .= "brother:" . $this->displayLine($cur_oid) . "\n";
			}

			$out_segs[] = $out;
		}

		// --- Output ---

		$out = $prefix;
		$first = true;
		foreach ($out_segs as $seg) {
			if (!$first) {
				$out .= ":----------------------\n"; # TODO: max len of output
			}
			$out .= $seg;
			$first = false;
		}

		if ($make_tabs) {
			$out = self::make_tabs($out);
		}

		return $out;
	}

	# http://whois.viathinksoft.de/gwhois_fork/showcode/?fn=package%2Fshare%2Fsubprograms%2Ffunctions.inc.php
	# Changed: $abstand default 4->2, oop, +$mindestbreite
	protected static function make_tabs($text, $abstand = 2, $mindestbreite = 0) {
		$ary = explode("\n", $text);
		$longest = 0;
		foreach ($ary as &$a) {
			$bry = explode(':', $a, 2);
			if (count($bry) < 2) continue;
			$c = strlen($bry[0]);
			if ($c > $longest) $longest = $c;
		}
		if ($longest < $mindestbreite) $longest = $mindestbreite;
		foreach ($ary as $n => &$a) {
			$bry = explode(':', $a, 2);
			if (count($bry) < 2) continue;
			$rep = $longest-strlen($bry[0]) + $abstand;
			if ($rep < 1) {
				$wh = '';
			} else {
				$wh = str_repeat(' ', $rep);
			}
			$ary[$n] = ($bry[0] != '' ? $bry[0].':' : ' ').$wh.trim($bry[1]);
		}
		$x = implode("\n", $ary);
		return $x;
	}


	protected static $fieldExtenders = array();

	public static function registerFieldExtender($prov) {
		self::$fieldExtenders[] = $prov;
	}

}
