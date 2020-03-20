<?php

# todo: "recursive" functions private machen, damit params nicht manipuliert werden können...
# todo: return false -> exception

# TODO false vs null ausgaben
# TODO signed openssl output

# TODO: oid definieren für name based
# QUE: geht extention von macros?

# TODO: "server time" am ende von Âquery() anzeigen?

require_once __DIR__ . '/../includes/uuid_utils.inc.php';
require_once __DIR__ . '/../includes/oid_utils.inc.php';

define('UUID_NAMEBASED_NS_OidPlusMisc',   'ad1654e6-7e15-11e4-9ef6-78e3b5fc7f22');
define('UUID_NAMEBASED_NS_OidPlusNSOnly', '0943e3ce-4b79-11e5-b742-78e3b5fc7f22');

define('GENERATION_ROOT_DEFAULT', '.2.25.<SYSID>');

class VolcanoDB {
	protected $macro_data = array(); // TODO: use with caution!
	protected $oid_data = array(); // TODO: use with caution!
	protected $authTokens = array();
	protected $configuration = array();
	protected $configuration_file = null;
	protected $configuration_may_create = false;

	protected $redactedMessage = '# Information redacted. Please append a correct auth token with your request.';
#	protected $redactedMessage = null;

	public function filterRedactedEntries($output) {
		$out = '';

		$lines = explode("\n", $output);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line == '') continue;
			$name_val = explode(':', $line, 2);
			if (count($name_val) != 2) continue;
			if (trim($name_val[1]) != trim($this->redactedMessage)) {
				$out .= $line."\n";
			}

		}

		return $out;
	}

	public function getOIDData() {
		return $this->oid_data;
	}

	public function getMacroData() {
		return $this->macro_data;
	}

	public function __construct($conf_file, $may_create) {
		$this->configuration_file = $conf_file;
		$this->configuration_may_create = $may_create;
		$this->readConfiguration();
	}

	public function getConfigValue($name) {
		if (!isset($this->configuration[$name])) return false;
		return $this->configuration[$name];
	}

	public function setConfigValue($name, $value) {
		$this->configuration[$name] = $value;
		$this->saveConfiguration();
	}

	protected function readConfiguration() {
		if (is_null($this->configuration_file)) return;
		$this->configuration = array();
		if (!file_exists($this->configuration_file)) {
			if (!$this->configuration_may_create) {
				$metadata = array();
				throw new VolcanoException("Configuration file '$conf_file' not found", $metadata);
			}
		} else {
			$lines = file($this->configuration_file);
			foreach ($lines as &$line) {
				$line = trim($line);
				if (empty($line)) continue;
				if ($line[0] == '#') continue;
				$ary = explode('=', $line, 2);
				if (count($ary) < 2) continue;
				$this->configuration[$ary[0]] = $ary[1];
			}
		}

		if ($this->getConfigValue('system_unique_id') === false) {
			$this->setConfigValue('system_unique_id', gen_uuid());
		}
	}

	public function getSystemID() {
		return $this->getConfigValue('system_unique_id');
	}

	public function getSystemIDInteger() {
		$val = uuid_numeric_value($this->getSystemID());
		if (!$val) {
			$metadata = array();
			throw new VolcanoException("system_unique_id is not a valid UUID. Please check db/local.conf", $metadata);
		}
		return $val;
	}

	protected function saveConfiguration() {
		if (is_null($this->configuration_file)) return;
		file_put_contents($this->configuration_file, '');
		foreach ($this->configuration as $name => $val) {
			file_put_contents($this->configuration_file, $name.'='.$val."\n", FILE_APPEND);
		}
	}

	public function setRedactedMessage($msg) {
		$this->redactedMessage = $msg;
	}

	public function getRedactedMessage() {
		return $this->redactedMessage;
	}

	public function disableRedactedMessage() {
		$this->redactedMessage = null;
	}

	public function isRedactedMessageEnabled() {
		return !is_null($this->redactedMessage);
	}

	public function addAuthToken($token) {
		if (!in_array($token, $this->authTokens)) {
			$this->authTokens[] = $token;
			$this->clearCaches();
		}
	}

	public function clearAuthTokens() {
		$this->authTokens = array();
		$this->clearCaches();
	}

	public function addDir($dir, $recursive=true) {
		if (!is_dir($dir)) {
			throw new VolcanoException("Directory not found: $dir");
		}
		$ary = glob($dir . '/*');
		if (count($ary) == 0) return;
		if ($ary === false) return;
		sort($ary);
		foreach ($ary as &$a) {
			if ($a[0] == '.') continue; // e.g. '.', '..', or '.htaccess'
			if (self::endsWith($a, '~')) continue; // recycled files
			if (($recursive) && (is_dir($a))) {
				$this->addDir($a, $recursive);
				continue;
			}
			if (!is_file($a)) continue;
			if (!self::endsWith($a, '.db')) continue;
			$this->addFile($a);
		}
		unset($a);

#echo '<!--';
#print_r($this->oid_data);
#print_r($this->macro_data);
#echo '-->';

	}

	public function addFile($file) {
		$h = fopen($file, 'r');

		$lineno = 1;
		$header = fgets($h);
		if (trim($header) != '['.$this->getFileformatIdentifier().']') {
			$metadata = array();
			$metadata['source'] = "$file:0";
			throw new VolcanoException("Header of file is invalid", $metadata);
		}

		while (!feof($h)) { // TODO OK?
			$line = fgets($h);
			$lineno++;
			$this->addLine($line, $file.':'.$lineno);
		}
		fclose($h);
	}

# todo: wie das problem bzgl allowed field-override lösen? (superior RA verbietet "identifier" change)

	protected function isSpecialInvisibleField($data) {
		assert(isset($data['attrib_name']));

		$field_name = $data['attrib_name'];

		if (empty($field_name)) return false;

		if ($field_name == '*read-auth')  return true;
	#	if ($field_name == '*write-auth') return true;
		if ($field_name == '*invisible')  return true;

		if ($field_name[0] == '*') {
			throw new VolcanoException("Invalid system command '$field_name'", $data);
		}

		return false;
	}

	public function isAuthentificated($oid) {
		$readAuths = $this->getDatasets($oid, '*read-auth', false); # todo: [co] erzwingen?
		foreach ($readAuths as &$ra) {
			$nid = isset($ra['attrib_params'][0]) ? $ra['attrib_params'][0] : '';
			$found_valid_authprovider = false;
			foreach (self::$authProvs as &$ap) {
				if ($ap->checkId($nid)) {
					$found_valid_authprovider = true;
					if ($ap->checkAuth($ra['value'], null)) return true; // first, check with empty/null authToken, e.g. for IP-Authentification
					foreach ($this->authTokens as &$token) {
						if ($ap->checkAuth($ra['value'], $token)) return true;
					}
					unset($token);
				}
			}
			if (!$found_valid_authprovider) {
				# return false;
				throw new VolcanoException("No authentification provider found", $ra);
			}
			unset($ap);
		}
		unset($ra);
		return false;
	}

	protected static function showSource($source) {
		if (strpos($source, ':') === false) return $source;
		preg_match('@^(.+):(\\d+)$@', $source, $m);
		$file = $m[1];
		$line = $m[2];
		return "$file at line $line";
	}

	protected function clearCaches() { # TODO: aufsplitten in caches, die die auth betreffen und die, die es nicht tun?
		$this->all_cache = null;
		$this->cache_recdatasets = array();
		$this->cache_listOIDs = array();
	}

	protected function getIndexGenerationRoot() {
		$val = $this->getConfigValue('local_index_generation_root');
		if (!$val) {
			$val = GENERATION_ROOT_DEFAULT;
			$this->setConfigValue('local_index_generation_root', $val);
		}

		return $this->extendOID($val, true);
	}

	public function extendOID($identifier, $no_gen_root_replacement=false) {
		$identifier = str_replace('<SYSID>', $this->getSystemIDInteger(), $identifier);
		if (!$no_gen_root_replacement) {
			// Avoids an endless recursion with getIndexGenerationRoot
			$identifier = str_replace('<GENROOT>', $this->getIndexGenerationRoot(), $identifier);
		}
		$identifier = sanitizeOID($identifier, true);
		return $identifier;
	}

	public function addLine($line, $source='(Direct Input):0') {
		$line = trim($line);
		$line = str_replace("\t", ' ', $line);
		$line = str_replace(array("\n", "\r"), '', $line);

		$line_expl = explode(' ', $line, 2);

		if (empty($line) || ($line[0] == '#')) {
			return;
		} else if (strpos($line_expl[0], ':') !== false) {
			$this->clearCaches();

			$expl2 = explode(':', $line_expl[0], 2);
			$namespace  = trim(strtolower($expl2[0]));
			$identifier = trim($expl2[1]);

			if (empty($namespace)) {
				$metadata = array();
				$metadata['source'] = $source;
				throw new VolcanoException("The namespace may not be empty", $metadata);
			}

			if (empty($identifier)) {
				$metadata = array();
				$metadata['source'] = $source;
				throw new VolcanoException("The identifier may not be empty", $metadata);
			}

			$is_oid      = $namespace == 'oid';
			$is_macro    = $namespace == '*macro';
			$is_external = $namespace == '*external';
			$is_other    = (!$is_oid) && (!$is_macro) && (!$is_external);

			# TODO: isSpecialNamespace($namespace, $source) ?
			if (($namespace[0] == '*') && (!$is_macro) && (!$is_external)) {
				$metadata = array();
				$metadata['source'] = $source;
				throw new VolcanoException("Invalid system namespace '$namepsace'", $metadata);
			}

			if ($is_external) {
				# TODO: wenn lokal und mit .php endend, dann eval()
				$url = $identifier;
				$this->addFile($url);
				return;
			}

			if ($is_macro) {
				$identifier = strtoupper($identifier);
			}

			if ($is_other) {
				# TODO!!! diese handlers als plugins auslagern!
				# QUE: sollte sowas nicht OID+ und nicht Volcano sein?

				$flags = '';
				if (strpos($identifier, '[co]') !== false) {
					$identifier = str_replace('[co]', '', $identifier);
					$flags .= '[co]';
				}
				$original_identifier = $identifier;
				# TODO: check for unknown attrib flags

/*
				if (($namespace == 'guid') || ($namespace == 'uuid')) {
					$guid = $identifier;
				} else {
					$guid = gen_uuid_sha1_namebased(UUID_NAMEBASED_NS_OidPlusMisc, $namespace.':'.$identifier);
				}
				$identifier = '.'.uuid_to_oid($guid); # TODO: parameter, ob mit leading dot oder nicht
*/

				$is_guid_ns = ($namespace == 'guid') || ($namespace == 'uuid');

				if ($is_guid_ns && (!$this->getConfigValue('uuid_indexes_in_genroot'))) {
					# uuid_indexes_in_genroot wird verwendet, sonst würde bei einer sammlung von MS COM clsids würde sonst die root zone platzen
					$guid = $identifier;
					$identifier = '.'.uuid_to_oid($guid); # TODO: parameter, ob mit leading dot oder nicht
				} else {
					# Es wird ein weltweit einzigartiger Root angelegt, anstelle 2.25 für jeden einzelnen index zu verwenden.
					# Grund: Indexes sollen (alleine schon aus Performancegründen bei der Root-Anzeige im Webinterface)
					# in einer Wurzel zusammengefasst werden. Diese Wurzel sollte aber unter der legalen Kontrolle des
					# Eigentümers stehen. "2.25" kann nicht als Wurzel definiert werden, da der Benutzer kein Recht hat,
					# "2.25" sein Eigen zu nennen.
					# PROBLEM: aber dann kann man von den einzelnen items nicht das "uuid" feld ablesen, um an die
					# per UUID_NAMEBASED_NS_OidPlusMisc erstellte UUID ranzukommen, da das "uuid" feld nur kindsknoten von 2.25
					# konvertiert.
					$gen_root = $this->getIndexGenerationRoot();

					if ($namespace == 'doi') {
						if (!self::beginswith($identifier, '10.')) {
							$metadata = array();
							$metadata['source'] = $source;
							throw new VolcanoException("Invalid DOI '$identifier'. Must start with '10.'", $metadata);
						}
						$x = substr($identifier, 3/* strlen('10.') */);
						$orgid = explode('/', $x, 2)[0];

						# doi(10) <orgid> <doi:identifier>
						$identifier_ns = $gen_root.'.10.'.$orgid;

						if (!$this->oidDescribed($identifier_ns, false)) {
							$this->addLine("oid:$identifier_ns description: Organisation $orgid", $source);
						}

						$guid_item = gen_uuid_sha1_namebased(UUID_NAMEBASED_NS_OidPlusMisc, 'doi:'.$identifier);
					} else if ($is_guid_ns) {
						# uuid(25) <numeric_uuid>
						$identifier_ns = $gen_root.'.25';

						# TODO: im web frontend trotzdem die originale index uuid zum klicken abieten (action=uuid_info, nicht nur action=show_index!)
						$guid_item = $identifier;
					} else {
						$guid_ns = gen_uuid_sha1_namebased(UUID_NAMEBASED_NS_OidPlusNSOnly, $namespace);

						# ns(0) <ns> <ns:identifier>
						$identifier_ns = $gen_root.'.0.'.uuid_numeric_value($guid_ns);

						if (!$this->oidDescribed($identifier_ns, false)) {
							$this->addLine("oid:$identifier_ns description: Automatically generated arc for namespace \"$namespace\"", $source);
							if (oid_id_is_valid($namespace)) {
								$this->addLine("oid:$identifier_ns identifier:$namespace", $source);
							}
						}

						$guid_item = gen_uuid_sha1_namebased(UUID_NAMEBASED_NS_OidPlusMisc, $namespace.':'.$identifier);
					}

					$identifier = $identifier_ns.'.'.uuid_numeric_value($guid_item);
				}
				if (!$this->oidDescribed($identifier, false)) { # TODO: WARUM? man kann den index doch auch so anlegen??? !!
					$this->addLine('oid:'.$identifier." ${flags}index($namespace):$original_identifier", $source);
				}
				$is_other = false;
				$is_oid   = true;
			}

			# Muss als letztes stehen, da $is_other zu $is_oid konvertiert werden kann
			if ($is_oid) {
				$bak_oid = $identifier;
				$identifier = $this->extendOID($identifier);

				if ($identifier === false) {
					$metadata = array();
					$metadata['source'] = $source;
					$metadata['oid']    = $bak_oid;
					throw new VolcanoException("Illegal OID or dot notation with leading dot not recognized", $metadata);
				}
			}

			if (!isset($line_expl[1])) {
				$metadata = array();
				$metadata['source'] = $source;
				throw new VolcanoException("Syntax error in DB: line contains no data", $metadata);
			}

			$data = isset($line_expl[1]) ? $line_expl[1] : '';

			$bry = explode(':', $data, 2);
			$attrib_name = (isset($bry[0])) ? $bry[0] : '';
			$value       = (isset($bry[1])) ? $bry[1] : '';

			// <attrib_name>(<attrib_params>)
			if (preg_match("@^(.*)\((.+)\)(.*)$@isU", $attrib_name, $m)) {
				$attrib_name   = $m[1].$m[3];
				$attrib_params = explode(',', $m[2]);
			} else {
				$attrib_params = array();
			}

			// Process params
			// [co]<attrib_name> marks the line as confidential (read-auth necessary)
			$attrib_name = str_replace('[co]', '', $attrib_name, $cnt);
			$flag_confidential = $cnt > 0;

			$attrib_name = str_replace('[xt]', '', $attrib_name, $cnt);
			$flag_extend = $cnt > 0;

			$attrib_name = str_replace('[add]', '', $attrib_name, $cnt);
			$flag_add = $cnt > 0;

			$attrib_name = str_replace('[del]', '', $attrib_name, $cnt);
			$flag_del = $cnt > 0;

			$attrib_name = str_replace('[in]', '', $attrib_name, $cnt);
		##	$flag_inherit = $cnt > 0;
			# Wir speichern hier keinen boolean, sondern den $attrib_name .
			# Dadurch wird sichergestellt, dass ein [xt]-Block im ganzen überschrieben wird, und nicht jede Zeile einzeln.
			# Jede Zeile enthält also im flag_inherit den Namen ihres [xt]-Blocks.
			$flag_inherit = ($cnt > 0) ? $attrib_name : false; // attrib_name muss ohne restliche flags sein, deswegen [in] zuletzt parsen

			# No! We just leave inherited so we can use it later
			/*
			if (($flag_inherit) && ($is_macro)) {
				$metadata = array();
				$metadata['source'] = $source;
				throw new VolcanoException("Macro attributes cannot be inherited", $metadata);
			}
			*/

			if (preg_match('@\[(.*)\]@isU', $attrib_name, $m)) {
				$metadata = array();
				$unkn_flag = $m[1];
				$metadata['source'] = $source;
				throw new VolcanoException("Flag [$unkn_flag] unknown", $metadata);
			}

			$attrib_name = trim($attrib_name);

			// Add data
			if ($flag_extend) {
				$value = trim($value);

				// Allow multiple whitespaces as separator for macro params
				$value = preg_replace('@(\s+)@', ' ', $value);

				# $macro_params = explode(' ', $value); // <macroname> <macroparam_1> <...>
				$macro_params = str_getcsv($value, ' '); // requires PHP 5.3, see http://stackoverflow.com/questions/2202435/php-explode-the-string-but-treat-words-in-quotes-as-a-single-word for alternatives
				$macroname = $macro_params[0];
				if (!isset($this->macro_data[strtoupper($macroname)])) {
					$metadata = array();
					$metadata['source'] = $source;

					# TODO: zuerst alle anderen *.db files durchprobieren ob es auftaucht?
					throw new VolcanoException("Macro '$macroname' not found", $metadata);
				}

				$x = $this->macro_data[strtoupper($macroname)];

				foreach ($x as $y) { // no &$y
				#	$y['source'] bleibt erhalten

					// Replace macro params in value, attrib_name and attrib_params
					foreach ($macro_params as $mp_n => &$mp_x) {
						$y['value']         = str_replace('__'.$mp_n.'__', $mp_x, $y['value']);
						$y['attrib_name']   = str_replace('__'.$mp_n.'__', $mp_x, $y['attrib_name']);
						foreach ($y['attrib_params'] as &$ap_val) {
							$ap_val = str_replace('__'.$mp_n.'__', $mp_x, $ap_val);
						}
					}
					// Remove unused macro param place holders
					// NO! Otherwise we cannot use parametrized macros which are using parametrized macros itself
					/*
					$y['value']         = preg_replace('@__(\\d+)__@sU', '', $y['value']);
					$y['attrib_name']   = preg_replace('@__(\\d+)__@sU', '', $y['attrib_name']);
					foreach ($y['attrib_params'] as &$ap_val) {
						$ap_val = preg_replace('@__(\\d+)__@sU', '', $ap_val);
					}
					*/

					// $y['attrib_params']     = $attrib_params;
					// Nein, attrib_params lieber direkt nach ?? einfügen:
					if (count($attrib_params) > 0) {
						$attr_ext = '('.implode(',', $attrib_params).')';
					} else {
						$attr_ext = '';
					}
					# NG: '??' auch ersetzen in attrib_param, value ?
					$y['attrib_name']       = str_replace('??', $attrib_name.$attr_ext, $y['attrib_name']);
					// QUE: wie verhalten sich die params im falle einer vererbung? kann man params missbrauchen um z.B. ra(1), ra(2) etc zu kennzeichnen?

					$y['flag_confidential'] = $flag_confidential || $y['flag_confidential'];
					# todo: read-auth im macro-bereich?
				#	$y['flag_extend']       = true; // TODO?
					$y['flag_inherit']      = trim($flag_inherit); # dieser flag_inherit geht von der ursprungs-oid über alle stufen der macros und submacros hinweg
					$y['flag_add']          = $flag_add;

					if ($is_macro) {
						$y['macro'] = $identifier;
					} else {
						$y['oid']   = $identifier;
					}

					// Check if the identifier is valid to the ASN.1 standards
					$flag_extend = strpos($y['attrib_name'], '[xt]') !== false;
					if ((!$flag_extend) && (!$is_macro) && (strtolower($y['attrib_name']) == 'identifier') && (!oid_id_is_valid($y['value']))) {
						throw new VolcanoException("Identifier '".$y['value']."' is not a valid ASN.1 identifier", $y);
					}

					if ((!$flag_extend) && (!$is_macro) && (strtolower($y['attrib_name']) == 'iri') && (!iri_valid($y['value']))) {
						throw new VolcanoException("Identifier '".$y['value']."' is not a valid IRI identifier", $y);
					}

					if ($is_macro) {
						$this->macro_data[$identifier][] = $y;
					} else {
						$this->oid_data[$identifier][] = $y;
					}
				}
			} else {
				// $value = trim($value); // TODO: oder doch lieber? z.b. descriptions einrücken?

				# NG: '??' auch ersetzen in attrib_param, value ?
				if ((!$is_macro) && (strpos($attrib_name, '??') !== false)) {
					$metadata = array();
					$metadata['source'] = $source;
					throw new VolcanoException("'??' is only allowed inside a macro", $metadata);
				}

				$y = array(
					'source'	    => $source,
					'attrib_name'       => $attrib_name,
					'attrib_params'     => $attrib_params,
					'value'             => $value,
					'flag_confidential' => $flag_confidential,
				#	'flag_extend'       => $flag_extend,
					'flag_inherit'      => trim($flag_inherit),
					'flag_add'          => $flag_add,
					'flag_del'          => $flag_del
				);

				if ($is_macro) {
					$y['macro'] = $identifier;
				} else {
					$y['oid']   = $identifier;
				}

				// Check if the identifier is valid to the ASN.1 standards
				if ((!$flag_extend) && (!$is_macro) && (strtolower($y['attrib_name']) == 'identifier') && (!oid_id_is_valid($y['value']))) {
					throw new VolcanoException("Identifier '".$y['value']."' is not a valid ASN.1 identifier", $y);
				}

				if ($is_macro) {
					$this->macro_data[$identifier][] = $y;
				} else {
					$this->oid_data[$identifier][] = $y;
				}
			}
		} else {
			$metadata = array();
			$metadata['source'] = $source;
			throw new VolcanoException("Syntax error in database (not beginning with 'oid:' or '*macro:' or a valid identifier like 'guid:<uuid>')", $metadata);
		}
	}

	private $all_cache = array();
	# TODO: auch $parent_oid parameter
	public function getAllOIDs($check_auth=true) {
		$vvv = $check_auth;
		if (isset($this->all_cache[$vvv])) return $this->all_cache[$vvv];

		// TODO: $with_macros per default false. ausblenden. xxx

		$ary = array();
		foreach ($this->oid_data as $b => &$data) {
			if ($check_auth) {
				if ($this->oidDescribed($b, $check_auth)) $ary[] = $b;
			} else {
				$ary[] = $b;
			}
		}

		$this->all_cache[$vvv] = $ary;
		return $ary;
	}

	// TODO: -> functions.inc.php
	protected static function getShortestString(&$ary) {
		$minlen = PHP_INT_MAX;
		$out = false;
		foreach ($ary as &$a) {
			if ($a === null) continue;
			$len = strlen($a);
			if ($len < $minlen) {
				$out = $a;
				$minlen = $len;
			}
		}
		unset($a);
		return $out;
	}

	// TODO: -> oid_utils.inc.php ?
	protected static function strikeRoot(&$ary, $oid) {
		$oid = sanitizeOID($oid, substr($oid,0,1) == '.');
		if ($oid === false) return false;

		$dotstop = self::appendDot($oid);
		foreach ($ary as &$a) {
			if (($a == $oid) || (self::beginsWith($a, $dotstop))) {
				$a = null;
			}
		}
		unset($a);
	}

	public function findRoots($check_auth=true) {
		$out = array();

		$ary = $this->getAllOIDs($check_auth);

		while (($oid = $this->getShortestString($ary)) !== false) {
			$out[] = $oid;
			$this->strikeRoot($ary, $oid);
		}

		oidSort($out);

		return $out;
	}

	public static function findSearchProvider($nid) {
		$found_prov = null;
		foreach (self::$searchProvs as &$searchprov) {
			if ($searchprov->checkId($nid)) {
				$found_prov = $searchprov;
				break;
			}
		}
		unset($searchprov);

		return $found_prov;
	}

	public function findOID(&$indexname, $check_auth=true) {
		$lowest_oid  = null;
		$lowest_dist = PHP_INT_MAX;
		$lowest_nid  = null;
		$oids = $this->getAllOIDs($check_auth);

		$ary = explode(':', $indexname, 2);
		if (count($ary) == 2) {
			$suggested_nid = $ary[0]; // This does not neccessarily be a searchprovider NID, since it could also be a part of an IPv6 address
			$found_prov = self::findSearchProvider($suggested_nid);
			if (!is_null($found_prov)) {
				$indexname = $ary[1];
			} else {
				// No search provider was found. We don't strip the text before ':', because it
				// could be actually a part of the index name, e.g. for an IPv6
				# $indexname = $ary[1];
			}
		} else {
			$suggested_nid = '';
			$found_prov = null;
		}

		$has_preferred_prov = !is_null($found_prov);

		foreach ($oids as &$oid) {
			$search_data = $this->getDatasets($oid, 'index'); # todo: wird [co] beachtet?
			foreach ($search_data as &$data) {
				$nid   = $data['attrib_params'][0];
				$value = $data['value'];


				if (!$has_preferred_prov) $found_prov = self::findSearchProvider($nid);

				if (!is_null($found_prov)) {
					$cur_distance = $found_prov->calcDistance($value, $indexname);
					if ($cur_distance === false) continue;
					if ($cur_distance < 0) continue; // item is too specific for the request
					// else if ($cur_distance == 0) return array($oid, 0, $nid);
					else if ($cur_distance < $lowest_dist) {
						$lowest_dist = $cur_distance;
						$lowest_oid  = $oid;
						$lowest_nid  = $nid;
						if ($cur_distance == 0) break 2;
					}
				} else {
					// We don't have a Searchprovider, but we can look for an index which matches exactly
					if ($suggested_nid == $nid) {
						$indexname = isset($ary[1]) ? $ary[1] : '';
					}
					if ($value == $indexname) {
						$lowest_dist = 0;
						$lowest_oid = $oid;
						$lowest_nid = $nid;
						break 2;
					}
				}
			}
			unset($data);
		}
		unset($oid);

		if (is_null($lowest_oid)) return null;
		return array($lowest_oid, $lowest_dist, $lowest_nid);
	}

	private $cache_listOIDs = array();
	public function listOIDs($parent_oid, $absolute=true, $depth=-1, $check_auth=true) {
		$parent_oid = sanitizeOID($parent_oid, substr($parent_oid,0,1) == '.');
		if ($parent_oid === false) return false;

		$vvv = ($absolute ? 'T' : 'F').($check_auth ? 'T' : 'F').$depth.'/'.$parent_oid;
		if (isset($this->cache_listOIDs[$vvv])) return $this->cache_listOIDs[$vvv];

		$dotstop = self::appendDot($parent_oid);

		$oids = $this->getAllOIDs($check_auth);

		$out = array();
		foreach ($oids as &$oid) {
			if (self::beginsWith($oid, $dotstop)) {
				if ($depth >= 0) {
					if (substr_count($oid, '.')-substr_count($dotstop, '.') > $depth-1) continue;
				}
				if ($absolute) {
					$out[] = $oid;
				} else {
					$out[] = substr($oid, strlen($dotstop));
				}
			}
		}
		unset($oid);

		$this->cache_listOIDs[$vvv] = $out;
		return $out;
	}

	public function listChildren($parent_oid, $levels=-1, $check_auth=true) {
		if ($levels == 0) return false;

		$parent_oid = sanitizeOID($parent_oid, substr($parent_oid,0,1) == '.');
		if ($parent_oid === false) return false;

		$deepChildrenSearch = $this->listOIDs($parent_oid, false, -1, $check_auth);
		if (count($deepChildrenSearch) == 0) return array();

		$firstarcs = array();
		foreach ($deepChildrenSearch as &$child) {
			$ary = explode('.', $child, 2);
			$firstarc = $ary[0];
			if ($firstarc == '') continue; # Achtung: Darf nicht empty() sein, da empty(0)===true

#			if (!in_array($firstarc, $firstarcs)) $firstarcs[] = $firstarc; # slow
			if (!isset($firstarcs[$firstarc])) $firstarcs[$firstarc] = true;
		}
		unset($child);
#		sort($firstarcs);
		ksort($firstarcs);

		$dotstop = self::appendDot($parent_oid);

		$out = array();
#		foreach ($firstarcs as &$firstarc) {
		foreach ($firstarcs as $firstarc => &$dummy) {


/*	This doesn't work with orphan OIDs
		$firstarcs = $this->listOIDs($parent_oid, false, 1, $check_auth);
		if (count($firstarcs) == 0) return array();

		sort($firstarcs);

		$dotstop = self::appendDot($parent_oid);

		$out = array();
		foreach ($firstarcs as &$firstarc) {
*/
			$cur_oid = $dotstop.$firstarc;

			$out[$firstarc] = array(
				'children'      => $this->listChildren($cur_oid, $levels-1, $check_auth),
				'described'     => $this->oidDescribed($cur_oid, $check_auth),
				'identifiers'   => $this->getIdentifiers($cur_oid, $check_auth),
				'unicodelabels' => $this->getUnicodeLabels($cur_oid, $check_auth),
				'attributes'    => $this->getOIDAttribs($cur_oid, $check_auth)
			);
		}

		return $out;
	}

	/* protected */ public function getIdentifiers($oid, $check_auth=true) {
		// TODO: value sanity check
		return $this->getValuesOf($oid, 'identifier', $check_auth);
	}

	/* protected */ public function getUnicodeLabels($oid, $check_auth=true) {
		// TODO: value sanity check
		return $this->getValuesOf($oid, 'unicodelabel', $check_auth);
	}

	/* protected */ public function getOIDAttribs($oid, $check_auth=true) {
		return $this->getValuesOf($oid, 'attribute', $check_auth, 1);
	}

	// case 0 = preserve		TODO: als konstanten auslagern
	// case 1 = upper case
	// case 2 = lower case
	/* protected */ public function getValuesOf($oid, $attrib_name, $unique=true, $case=0) {
		$oid = sanitizeOID($oid, substr($oid,0,1) == '.');
		if ($oid === false) return false;

		$identifiers = array();
		$identifiers_raw = $this->getDatasets($oid, $attrib_name);
		foreach ($identifiers_raw as &$idd) {
			$val = $idd['value'];
			if ($case == 1) $val = strtoupper($val);
			elseif ($case == 2) $val = strtolower($val);
			$identifiers[] = $val;
		}
		unset($idd);

		if ($unique) $identifiers = array_unique($identifiers);

		return $identifiers;
	}

	/* protected */ public function getValues($oid, $attribute_name) {
		$oid = sanitizeOID($oid, substr($oid,0,1) == '.');
		if ($oid === false) return false;

		$values = array();
		$values_raw = $this->getDatasets($oid, $attribute_name);
		foreach ($values_raw as &$idd) {
			$values[] = $idd['value'];
		}
		unset($idd);
		return $values;
	}

	public function oidDescribed($oid, $check_auth=true) {
		$oid = sanitizeOID($oid, substr($oid,0,1) == '.');
		if ($oid === false) return false;

		if ($check_auth) {
			$m = $this->getDatasets($oid, '', $check_auth);
			$c = 0;
			foreach ($m as &$data) {
				if ($this->isSpecialInvisibleField($data)) continue;
				$c++;
			}
			if ($c == 0) return false; // secret OID or not available OID
		}

		return isset($this->oid_data[$oid]); # TODO: was ist wenn OID secret?
	}

	public function getDatasets($oid, $attrib_name='', $check_auth=true) { // TODO filter nach params, value?
		$out = array();
		$ary = $this->rec_getDatasets($oid, $attrib_name, $check_auth);
		foreach ($ary as &$d) {
			if ((!empty($attrib_name)) && ($d['attrib_name'] != $attrib_name)) continue;
			$out[] = $d;
		}
		return $out;
	}

	public function stripAttribs($oid, $attrib_name, $limit=-1) {
		$count = 0;
		foreach ($this->oid_data[$oid] as $n => $data) { // no &$data
			if (empty($attrib_name) || ($data['attrib_name'] == $attrib_name)) {
				$count++;
				if (($limit >= 0) && ($count > $limit)) break;
				unset($this->oid_data[$oid][$n]);
			}
		}
	}

	private $cache_recdatasets = array();

	# FUT QUE: return by reference?
	protected function rec_getDatasets($oid, $attrib_name='', $check_auth=true) { // TODO filter nach params, value?
		$oid = sanitizeOID($oid, substr($oid,0,1) == '.');
		if ($oid === false) {
			throw new VolcanoException("'$oid' is not a valid OID."); # TODO: exception source ("stacktrace") metadata -- idee: global $source variable?
		}

		$vvv = $oid.'/'.$attrib_name.'/'.($check_auth ? 'T' : 'F');
		if (isset($this->cache_recdatasets[$vvv])) return $this->cache_recdatasets[$vvv];

		if (!$this->oidDescribed($oid, false)) return array();

		$out = array();
		foreach ($this->oid_data[$oid] as $data) { // no &$data
			if ($check_auth) {
				# Anstelle *invisible lieber ein "attribute:" ? ist leider schwieriger zu bedienen , z.b. wenn man es wieder weghaben möchte
				if (($data['attrib_name'] == '*invisible') && ($data['value'] == '1')) {
					if ($this->isAuthentificated($oid)) { # TODO: parameter, für was man sich authentifizieren möchte (read,write, etc).
						$dd = $data; // TODO: OK?
						$dd['attrib_name']   = 'attribute';
						$dd['value']         = 'INVISIBLE';
						$dd['flag_inherit']  = $data['flag_inherit'];
						$dd['flag_add']      = $data['flag_add']; # TODO: ok?
						$dd['attrib_params'] = array();
						$dd['source']        = $data['source'];
						# TODO: weitere dinge in $dd notwendig?
						$out[] = $dd;
					} else {
						$this->cache_recdatasets[$vvv] = array($data);
						return array($data);
					}
				}

				if (($data['flag_confidential']) && (!$this->isAuthentificated($oid))) {
					if (!$this->isRedactedMessageEnabled()) {
						continue; // hide complete line
					} else {
						$data['value'] = $this->redactedMessage;
					}
				}
			}

			if ((!empty($attrib_name)) && ($data['attrib_name'] != $attrib_name)) continue;

			// Add it to output
			if (!$data['flag_del']) $out[] = $data;
		}
		unset($data);

		// Get the inherited fields
		if ($oid != '.') {
			$ori_oid = $oid;
			while ($oid != '.') {
				$oid = oid_up($oid);
				if ($this->oidDescribed($oid, false)) break;
			}
			$ds = $this->rec_getDatasets($oid, $attrib_name, $check_auth);
			foreach ($ds as &$d) {
				if ($d['flag_inherit']) {
					// Only inherit if it isn't overwritten
					$is_overwritten = false;

					foreach ($this->oid_data[$ori_oid] as &$x) { // $d = diese oid ; $x = vater oid
#/*
						// compare single fields to each other (may also include fields that were extended from a macro)
						if ($x['attrib_name'] == $d['attrib_name']) { # todo: case sensitive?
							$is_overwritten = true;
							break;
						}
#*/

						if ($x['attrib_name'] == $d['flag_inherit']) { # todo: case sensitive?
							$is_overwritten = true;
							break;
						}

						// ein ganzer [xt] block wird inherited, z.B. "ra". nicht die einzelnen felder einzeln behandeln.
						if ($x['flag_inherit'] == $d['flag_inherit']) { # todo: case sensitive?
							$is_overwritten = true;
							break;
						}
					}

					if ($x['flag_add']) {
						$is_overwritten = false;
					}

					if ($is_overwritten) continue;

					if ($check_auth) {
						if (($d['attrib_name'] == '*invisible') && ($d['value'] == '1')) {
							if ($this->isAuthentificated($oid)) {
								$dd['attrib_name']   = 'attribute';
								$dd['value']         = 'INVISIBLE';
								$dd['flag_inherit']  = $d['flag_inherit'];
								$dd['flag_add']      = $d['flag_add']; # TODO: ok?
								$dd['attrib_params'] = array();
							#	$out[] = $dd;
							} else {
								$this->cache_recdatasets[$vvv] = array($d);
								return array($d);
							}
						}
					}

					// Add to output
					if (!$d['flag_del']) $out[] = $d;
				}
			}
		}

		$this->cache_recdatasets[$vvv] = $out;
		return $out;
	}

	// TODO: iso.org auch aufloesen und standardized
	public function resolveIdentifiers($term) {
		$ary = explode('.', $term);
		$cd = $this->listChildren('.');

		foreach ($ary as $n => &$a) {
			if ($n == 0) continue;
			if (!is_numeric($a[0])) {
				if (is_null($cd)) return false; // TODO FUT: loessen durch referenzierende whois server (forward)?
				$found = false;
				foreach ($cd as $arc => &$data) {
					foreach ($data['identifiers'] as &$idc) {
						if ($idc == $a) {
							$a = $arc;
							$found = true;
							break 2;
						}
					}
				}
				unset($data);
				if (!$found) return false;
			}
			$cd = (!isset($cd[$a]['children'])) ? null : $cd[$a]['children'];
		}
		unset($a);

		return implode('.', $ary);
	}

	// --------- STATIC

	protected static $searchProvs = array();
	protected static $authProvs = array();

	public static function getFileformatIdentifier() {
		return '1.3.6.1.4.1.37476.2.5.1.1.2';
	}

	public static function registerSearchProvider($prov) {
		self::$searchProvs[] = $prov;
	}

	public static function registerAuthProvider($prov) {
		self::$authProvs[] = $prov;
	}

	protected static function appendDot($oid) {
		return ($oid == '.') ? '.' : $oid . '.';
	}

	// TODO: functions.inc.php
	protected static function beginsWith($haystack, $needle) {
		# http://maettig.com/code/php/php-performance-benchmarks.php
		return strpos($haystack, $needle) === 0;
	}

	// TODO: functions.inc.php
	protected static function endsWith($haystack, $needle) {
		# $length = strlen($needle);
		# if ($length == 0) return true;
		# return (substr($haystack, -$length) === $needle);
		return substr($haystack, -strlen($needle)) === $needle;
	}
}
