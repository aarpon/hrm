<?php

// Module Util.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

//!---------------------------------------------------------
// @function    
// @desc
// @param       
// @return      
//!---------------------------------------------------------
function assign_rand_value($num) {
  // accepts 1 - 36
  switch($num) {
    case "1":
      $rand_value = "a";
      break;
    case "2":
      $rand_value = "b";
      break;
    case "3":
      $rand_value = "c";
      break;
    case "4":
      $rand_value = "d";
      break;
    case "5":
      $rand_value = "e";
      break;
    case "6":
      $rand_value = "f";
      break;
    case "7":
      $rand_value = "g";
      break;
    case "8":
      $rand_value = "h";
      break;
    case "9":
      $rand_value = "i";
      break;
    case "10":
      $rand_value = "j";
      break;
    case "11":
      $rand_value = "k";
      break;
    case "12":
      $rand_value = "l";
      break;
    case "13":
      $rand_value = "m";
      break;
    case "14":
      $rand_value = "n";
      break;
    case "15":
      $rand_value = "o";
      break;
    case "16":
      $rand_value = "p";
      break;
    case "17":
      $rand_value = "q";
      break;
    case "18":
      $rand_value = "r";
      break;
    case "19":
      $rand_value = "s";
      break;
    case "20":
      $rand_value = "t";
      break;
    case "21":
      $rand_value = "u";
      break;
    case "22":
      $rand_value = "v";
      break;
    case "23":
      $rand_value = "w";
      break;
    case "24":
      $rand_value = "x";
      break;
    case "25":
      $rand_value = "y";
      break;
    case "26":
      $rand_value = "z";
      break;
    case "27":
      $rand_value = "0";
      break;
    case "28":
      $rand_value = "1";
      break;
    case "29":
      $rand_value = "2";
      break;
    case "30":
      $rand_value = "3";
      break;
    case "31":
      $rand_value = "4";
      break;
    case "32":
      $rand_value = "5";
      break;
    case "33":
      $rand_value = "6";
      break;
    case "34":
      $rand_value = "7";
      break;
    case "35":
      $rand_value = "8";
      break;
    case "36":
      $rand_value = "9";
      break;
  }
  return $rand_value;
}

//!---------------------------------------------------------
// @function    
// @desc
// @param       
// @return      
//!---------------------------------------------------------
function get_rand_id($length) {
  if($length>0) { 
    $rand_id="";
    for($i=1; $i<=$length; $i++) {
      mt_srand((double)microtime() * 1000000);
      $num = mt_rand(1,36);
      $rand_id .= assign_rand_value($num);
    }
  }
  return $rand_id;
}


//!---------------------------------------------------------
// @function    huCoreTool  
// @desc        Calls a local hucore (that must be in the $PATH) to
//              execute some freeware image processing tools, or report
//              parameters. This local hucore doesn't need to have a license!
// @param       $tool is the procedure in scripts/hucore.tcl to be executed.
//              $options are extra command line options to send to that script
// @return      An array with all stdout lines
//!---------------------------------------------------------
function huCoreTools ($tool, $options) {
    global $hrm_path, $local_huygens_core;

    $cmd = "$local_huygens_core -task $hrm_path/scripts/hucore.tcl ".
           "-tool $tool $options";

    $answer = exec($cmd, $output, $result);

    # printDebug ($cmd, $output, "res: $result ans: $answer");

    if ($result == 0) {
        $begin = array_search("BEGIN PROC", $output);
        return array_slice($output, $begin);
    } else {
        echo ("<pre>huCoreTools error (last lines):\n");
        foreach ( array_slice($output, -4) as $line ) {
            echo ("$line\n");
        }
        echo "</pre>";
        return NULL;
    }

}

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
        global $hrm_path;
	global $logfile;
	global $logfile_max_size;

        $text = date("Y-m-d H:i:s"). " ".$text;

        $logpath = $hrm_path."/run/".$logfile;

	if ($log_verbosity == 0)
		return True;
	if (file_exists($logpath) 
            && (filesize($logpath) > $logfile_max_size * 1000 * 1000)) {
		if (file_exists($logpath . ".old")) {
			unlink($logpath . ".old");
		}
		rename($logpath, $logpath . ".old");
	}
	$file = fopen($logpath, 'a');
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

?>
