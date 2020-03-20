<?php

if (!defined('OID_REGEX')) {
	define('OID_REGEX', oid_detection_regex(HIGHLIGHT_OID_MINLEN, ALLOW_LEADING_ZEROES, LEADING_DOT_POLICY, true /* HIGHLIGHT_REQUIRES_WHITESPACE_DELIMITERS */));
}

function queryInfo($query) {
	return '<p class="green">Executed query <a href="?action=query&amp;query='.urlencode($query).'">'.htmlentities($query).'</a></p>';
}

function showHTML($cont, $db, $show_internal_links=true, $monospace=true) {
	$cont = htmlentities($cont);
#	$cont = str_replace(' ', '&nbsp;', $cont);
#	$cont = nl2br($cont);

	$rm = $db->getRedactedMessage();

	# TODO: htmlentities() bei den indexes usw
	# TODO: <...> problem - wird hinzugefügt

	# TODO: als plugins?

	// Recognize index links
	if ($show_internal_links) {
		$cont = preg_replace('@(index(\\((.+)\\)|):\\s*)([^\\s#].+)@', '\\1<a href="?action=show_index&amp;ns=\\3&amp;index=\\4">\\4</a>', $cont);
	}

	// Recognize web links
	$cont = preg_replace('@([a-zA-Z]+://[^\\s]+)@', '<a href="\\1">\\1</a>', $cont);

	// Recognize E-Mail links
	$cont = preg_replace('@([^\\s:]+)\\@([^\\s]+)@', '<a href="mailto:\\1(at)\\2">\\1(at)\\2</a>', $cont); # TODO: antispam

	// Recognize OID links (with or without leading dot)
	if ($show_internal_links) {
		$cont = preg_replace(OID_REGEX, '<a href="?action=show_oid&amp;oid=\\1">\\1</a>', $cont);
	} else {
		$cont = preg_replace(OID_REGEX, '<a href="http://www.oid-info.com/get/\\1">\\1</a>', $cont);
	}

	// Decorate the "redacted" message
	if ($show_internal_links) {
		$cont = str_replace($rm, "<a href=\"?action=auth_tokens\" style=\"text-decoration:none\"><span style=\"background:black;color:white\">$rm</span></a>", $cont);
	} else {
		$cont = str_replace($rm, "<span style=\"background:black;color:white\">$rm</span>", $cont);
	}

	// Recognize all UUIDs (except if the UUID is already linked as uuid-index)
	if ($show_internal_links) {
		$cont = preg_replace('@\\b([A-Fa-f0-9]{8}\\-[A-Fa-f0-9]{4}\\-[A-Fa-f0-9]{4}\\-[A-Fa-f0-9]{4}\\-[A-Fa-f0-9]{12})\\b(?!</a>|">)@', '<a href="?action=uuid_info&amp;uuid=\\1">\\1</a>', $cont);
	}

	if (($monospace) && ($cont != '')) {
		return '<pre>'.$cont.'</pre>';
	} else {
		return $cont;
	}
}

function showException($e) {
	ob_start();
	if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
	$title = 'Database error';
	echo page_header($title);
	$msg = $e;
	$msg = str_replace(__DIR__,  '.', $msg);
?>
<h2><?php echo $title; ?></h2>
<p>An internal error occurred while reading the Volcano database. Please contact the administrator and try again later.</p>
<p>Error message:</p>
<p><pre><?php echo $msg; ?></pre></p>
<?php
	echo page_footer();
	$cont = ob_get_contents();
	ob_end_clean();
	return $cont;
}

function page_header($title='', $systemID='') { # TODO: w3c
	ob_start();
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	<?php if ($systemID) echo '<meta name="X-OidPlus-SystemID" content="'.$systemID.' ('.uuid_numeric_value($systemID).')" />'."\n"; ?>
	<title><?php echo htmlentities($title); ?></title>
	<link href="style.css" rel="stylesheet" type="text/css" />
	<meta name="robots" content="noindex" /><!-- because system is outdated -->
</head>

<body>

<?php
	$cont = ob_get_contents();
	ob_end_clean();
	return $cont;
}

function page_footer() { # TODO: auch version anzeigen
	ob_start();
?>

<p style="text-align:center">OID+ web interface &copy; 2012 - <?php echo date('Y'); ?> <a href="http://www.viathinksoft.de/">ViaThinkSoft</a>.</p>

</body>

</html><?php
	$cont = ob_get_contents();
	ob_end_clean();
	return $cont;
}

