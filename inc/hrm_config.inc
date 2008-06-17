<?php

if (!isset($isServer))
	$isServer = False;

if ($isServer) 
	require_once('hrm_server_config.inc');
else 
	require_once('hrm_client_config.inc');




/* A global debugging function, that will print all its arguments whether they
 * are strings, arrays or objects. This works if a global variable $debug =
 * true, that can be defined in hrm_client_config.inc. Otherwise it does
 * nothing. */

/* This config file is not the ideal place to define functions, but I haven't
 * found any other file that is always included in all HRM subpages. */

if ( ! function_exists('printDebug') ) {
    function printDebug ( ) {
        global $debug;

        if ( ! $debug ) return;

        $args = func_get_args();

        echo "<small><kbd><font color=\"red\">Debugging: </font></kbd></small>";

        foreach ($args as $item) {

            if (is_object($item)) {
                $msg = (array) $item;
            } else {
                $msg = $item;
            }

            if (is_array($msg)) {
                echo "<pre>";
                print_r($msg);
                echo "</pre>";
            } else {
                echo "<kbd>$msg</kbd>";
            }
        }
    }
}


?>
