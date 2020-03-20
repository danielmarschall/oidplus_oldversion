<?php

require_once __DIR__.'/oid_utils.inc.php';

# TODO: diese konfiguration auch zur konfiguration von "is oid valid" nutzen
# 0 = "."
# 1 = ".2"
# 2 = ".2.999"
define('HIGHLIGHT_OID_MINLEN', 2);
define('ALLOW_LEADING_ZEROES', false);
define('LEADING_DOT_POLICY',   OID_DOT_OPTIONAL);
# define('HIGHLIGHT_REQUIRES_WHITESPACE_DELIMITERS', false);

define('OIDINFO_EXPORT_ENABLED', true);
define('OIDINFO_EXPORT_SUBMITTER_FIRST_NAME', 'Daniel');
define('OIDINFO_EXPORT_SUBMITTER_LAST_NAME', 'Marschall');
define('OIDINFO_EXPORT_SUBMITTER_EMAIL', 'info@daniel-marschall.de');
define('OIDINFO_EXPORT_SUBMITTER_ONLY_MONOSPACE', false); # False = auto determinate

define('OIDINFO_EXPORT_SIMPLEPINGPROVIDER', 'viathinksoft.de:49500'); // MUST show all oid-info.com values, not a local RA's OID repository!

