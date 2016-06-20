<?php
include('adodb.inc.php');
//include('adodb-exceptions.inc.php');
$db = adonewconnection('mysqli');
$db->connect('localhost', 'root', 'C0yote71', 'mantis_13x');
$db->debug = true;

set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
	$db->getAssoc('select * from mantis_user_table where 1=1');
	$db->getAssoc('select * from mantis_user_table where 0=1');
}
catch(Exception $e) {
	throw($e);
	exit(1);
}
