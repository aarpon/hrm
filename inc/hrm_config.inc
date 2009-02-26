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

if (!isset ($userManager) ) {
    # This default user manager script can be replaced in the configuration.
    # A demo server, for example, may use links to a demo directory instead of
    # creating an empty directory for each new user.
    $userManager = "bin/hrm";
}
?>
