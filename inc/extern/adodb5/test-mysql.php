<?php
require_once('adodb.inc.php');

$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
define( 'ADODB_ASSOC_CASE', ADODB_ASSOC_CASE_LOWER );

function test($type, $sql) {
	echo "--- $type ------- $sql ----\n";
	
	$db = ADONewConnection($type);
	$db->connect('localhost', 'root', 'C0yote71', 'mantis_13x');
	$rs = $db->execute($sql);
	if($rs === false) throw new Exception('query failed');

	echo "Row 1: "; print_r($rs->fields);
	$rs->MoveNext();
	echo "Row 2: "; print_r($rs->fields);
}


$sql = 'SHOW DATABASES';
$sql = 'SELECT *  FROM INFORMATION_SCHEMA.SCHEMATA';
$sql = 'SELECT name as NAME FROM mantis_project_table';

test('mysql', $sql);
test('mysqli', $sql);
