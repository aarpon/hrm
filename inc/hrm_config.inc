<?php

if (!isset($isServer))
	$isServer = False;

if ($isServer) 
	require_once('hrm_server_config.inc');
else 
	require_once('hrm_client_config.inc');

require_once ("Util.inc");

// Overrule options that do not apply.
if (isset ($enable_code_for_huygens) ) {
    $version = getHucoreVersionAsInteger( $enable_code_for_huygens );
    if ( $version < 3030100 ) {
        $useThumbails = false;
    }
} else {
    $useThumbails = false;
}


?>
