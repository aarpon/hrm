<?php

//!---------------------------------------------------------
// @function    report
// @desc        Write text to the logfile if level is not
//              bigger than log_verbosity. If logfile becomes
//              to big it is renamed and started over.
// @param       text  String  The text to be logged
// @param       level  Int    The log level of the text
// @return      Void
//!---------------------------------------------------------
function report($text, $level) {
	global $log_verbosity;
	global $logfile;
	global $logfile_max_size;

    $text = date("Y-m-d H:i:s"). " ".$text;

	if ($log_verbosity == 0)
		return True;
	if (file_exists($logfile) && (filesize($logfile) > $logfile_max_size * 1000 * 1000)) {
		if (file_exists($logfile . ".old")) {
			unlink($logfile . ".old");
		}
		rename($logfile, $logfile . ".old");
	}
	$file = fopen($logfile, 'a');
	if ($log_verbosity >= $level) {
		fwrite($file, $text);
		fwrite($file, "\n");
		fflush($file);
	}
	fclose($file);
}



/* A global debugging function, that will print all its arguments whether they
 * are strings, arrays or objects. This works if a global variable $debug =
 * true, that can be defined in hrm_client_config.inc. Otherwise it does
 * nothing. */

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



