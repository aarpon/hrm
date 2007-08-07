<?php

if (!isset($isServer))
	$isServer = False;

if ($isServer) 
	require_once('hrm_server_config.inc');
else 
	require_once('hrm_client_config.inc');

?>
