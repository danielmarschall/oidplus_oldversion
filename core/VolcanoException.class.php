<?php

class VolcanoException extends Exception {

	function __construct($message, $metadata=array()) {
		$msg = $message;
		if (isset($metadata['oid'])) $msg .= " for OID ".$metadata['oid'];
		if (isset($metadata['macro'])) $msg .= " for Macro ".$metadata['macro'];
		if (isset($metadata['source'])) $msg .= " at ".$this->showSource($metadata['source']);

		parent::__construct($msg);
	}

	protected static function showSource($source) {
		if (strpos($source, ':') === false) return $source;
		preg_match('@^(.+):(\\d+)$@', $source, $m);
		$file = $m[1];
		$line = $m[2];
		return "$file at line $line";
	}
}
