<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


/*!
 \brief  Alternative to the PHP function 'array_search' to find out whether a
         particular value exists in an array. PHP functions mistake '0' for ''.
 \param  $array The array where to search
 \param  $value The value to look for
 \return A boolean: true if the value exists in the array, false otherwise.
*/
function isValueInArray($array, $value){

 if(!is_array($array)){
   return;
 }

  $found = FALSE;

  foreach ($array as $arrKey => $arrValue) {

          /* A first filter on the length to distinguish '0' and '', which
           apparently cannot be told appart with '===' */
      if (strlen($arrValue) == strlen($value)) {
          if ($arrValue == $value) {
              $found = TRUE;
              break;
          }
      }
  }

  return $found;
}


/*!
  \brief  Takes a number between 1 and 36 as input and returns the corresponding
          code in the range 'a' .. 'z' '1' .. '9'
  \param  $num  An integer in the range 1 to 36.
  \return the corresponding character in the range 'a' .. 'z' '1' .. '9'
 */
function assign_rand_value($num) {
    // accepts 1 - 36
    switch ($num) {
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

/*!
  \brief  Returns a random string to be used as id
  \param  $length Length of the string
  \return a random string
*/
function get_rand_id($length) {
    if ($length > 0) {
        $rand_id = "";
        for ($i = 1; $i <= $length; $i++) {
            mt_srand((double) microtime() * 1000000);
            $num = mt_rand(1, 36);
            $rand_id .= assign_rand_value($num);
        }
    }
    return $rand_id;
}

/*!
  \brief  Escapes html strings generated in PHP so that they can be used as
          arguments in JavaScript functions.
  \param  $str  An html string.
  \return conveniently formatted string.
*/
function escapeJavaScript($str) {
    $str = str_replace("'", "\'", $str);
    $str = str_replace("\n", "\\n", $str);
    $str = str_replace('"', "'+String.fromCharCode(34)+'", $str);
    return $str;
}

/*!
  \brief  Calls a local hucore (that must be in the $PATH) to
          execute some freeware image processing tools, or report
          parameters. This local hucore doesn't need to have a license!
  \param  $tool is the procedure in scripts/hucore.tcl to be executed.
  \param  $options are extra command line options to send to that script
  \return an array with all stdout lines
*/
function huCoreTools($tool, $options) {
    global $hrm_path, $local_huygens_core;

    if (!isset($local_huygens_core)) {
        echo "Huygens tools can only work if you define a variable " .
        "'local_huygens_core' in the configuration files pointing to a local " .
        "hucore. Administrator: see hrm_client_config.inc.sample.";
        return;
    }

    $cmd = "$local_huygens_core -noExecLog -checkUpdates disable " .
            "-task \"$hrm_path/scripts/hucore.tcl\" " .
            "-huCoreTcl \"$hrm_path/scripts/hucore.tcl\" " .
            "-tool $tool $options 2>&1";

    $answer = exec($cmd, $output, $result);

    # printDebug ($cmd, $output, "res: $result ans: $answer");

    if ($result == 0) {
        $begin = array_search("BEGIN PROC", $output);
        if ($begin) {
            $ret = array_slice($output, $begin);
            return $ret;
        }
    }
    $more = 0;
    echo "<pre>ERROR with $cmd\n";
    if ($result != 0) {
        echo "$answer\n";
    }
    foreach ($output as $line) {
        if ($more > 0) {
            echo ("$line\n");
            $more -= 1;
            if ($more == 0)
                echo ("...\n");
        }
        if (stristr($line, "error")) {
            echo ("$line\n");
            $more = 2;
        }
    }
    echo "</pre>";
    return NULL;
}

/*!
  \brief  A wrapper around huCoreTools to retrieve an array, which is
          'calculated' by hucore in the background.
  \param  $tool It is the procedure in scripts/hucore.tcl to be executed.
  \param  $options These are extra command line options to send to that script
          hucore output. By default, it uses the same tool name.
  \return the requested array.
*/
function askHuCore($tool, $options = "") {

    $answer = huCoreTools($tool, $options);

    if (!$answer)
        return "(nothing)";
    # printDebug ($answer);

    $lines = count($answer);
    $msg = "";
    $ret = "";
    $sep = "";
    $retArr = array();
    $array_key = NULL;

    $ok = true;
    for ($i = 0; $i < $lines; $i++) {
        $key = $answer[$i];

        switch ($key) {
            case "ERROR":
                $i++;
                $retArr['error'][] = $answer[$i];
                $msg .= $answer[$i] . "<br>";
                $ok = false;
                break;
            case "REPORT":
                $i++;
                $retArr['report'][] = $answer[$i];
                echo $answer[$i] . "\n";
                @ob_flush();
                flush();
                break;
            case "KEY":
                $i++;
                $array_key = $answer[$i];
                break;
            case "VALUE":
                if ($array_key) {
                    $i++;
                    $retArr[$array_key] = $answer[$i];
                }
                break;
            default :
                break;
        }
    }

    if ($msg != "") {
        echo $msg;
    }

    return $retArr;
}

/*!
  \brief  Writes text to the logfile if level is not bigger than $log_verbosity
          (defined in the HRM configuration files). If logfile becomes to big
          it is renamed and a new one is started.
  \param  $text The text to be logged
  \param  $level  The log level of the text (0, 1, or 2)
*/
function report($text, $level) {
    global $log_verbosity;
    global $hrm_path;
    global $logdir;
    global $logfile;
    global $logfile_max_size;

    // Anything to log?
    if ($level > $log_verbosity) {
        return;
    }

    $text = date("Y-m-d H:i:s") . " " . $text;
    $logpath = $logdir . "/" . $logfile;

    // First rotate the log file if necessary.
    if (file_exists($logpath)
            && (filesize($logpath) > $logfile_max_size * 1000 * 1000)) {
        if (file_exists($logpath . ".old")) {
            unlink($logpath . ".old");
        }
        rename($logpath, $logpath . ".old");
    }

    $file = fopen($logpath, 'a');
    if ($file === FALSE) {
      // Cannot write to the log dir (or the file)
      return;
    }
    fwrite($file, $text);
    fwrite($file, "\n");
    fflush($file);
    fclose($file);
}

/*!
  \brief  Sends an email to the admin to notify any message. It helps in
          debugging strange events, and in being aware of abnormal scheduler
          stops. The message is also automatically reported to log.
  \param  $subject  A string to be appended to the email subject.
  \param  $message  The body of the email.
*/
function notifyRuntimeError($subject, $message) {
    global $email_sender;
    global $email_admin;
    $text = "Huygens Remote Manager warning:\n"
            . $name . " could not be pinged on " . date("r", time());
    $mail = new Mail($email_sender);
    $mail->setReceiver($email_admin);
    $mail->setSubject('Huygens Remote Manager - ' . $subject);
    $mail->setMessage($message);
    $mail->send();
    # No need to report to log, send() already did.
}

/*!
  \brief  Returns the name of current page
  \return string containing the name of current page
*/
function getThisPageName() {
    return substr($_SERVER["SCRIPT_NAME"],
        strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);
}

/*!
  \brief  This function transforms the php.ini notation for memory amount (e.g.
          '2M') to an integer (2*1024*1024 in this case) number of bytes
  \param  @v  memory amount in php.ini notation
  \return integer version in bytes
*/
function let_to_num($v) {
    $l = substr($v, -1);
    $ret = substr($v, 0, -1);
    switch (strtoupper($l)) {
        case 'P':
            $ret *= 1024;
        case 'T':
            $ret *= 1024;
        case 'G':
            $ret *= 1024;
        case 'M':
            $ret *= 1024;
        case 'K':
            $ret *= 1024;
            break;
    }
    return $ret;
}

/*!
  \brief  Report maximum upload size, in bytes.
  \return maximum upload size in bytes.
*/
function getMaxSingleUploadSize() {

    $max_upload_size = min(let_to_num(ini_get('post_max_size')),
        let_to_num(ini_get('upload_max_filesize')));

    return $max_upload_size;
}

/*!
  \brief  Report maximum post size, in bytes
  \return maximum upload post in bytes
*/
function getMaxPostSize() {

    global $max_post_limit;
    $ini_value = let_to_num(ini_get('post_max_size'));
    if (!isset($max_post_limit)) {
        $max_post_limit == 0;
    }
    if ($max_post_limit == 0) {
        return $ini_value;
    }
    $max_post_limit = 1024 * 1024 * $max_post_limit;
    if ($max_post_limit < $ini_value) {
        return $max_post_limit;
    } else {
        return $ini_value;
    }
}

/*!
  \brief  Report maximum file size that can be uploaded, in bytes
  \return maximum file size that can be uploaded in bites
*/
function getMaxFileSize() {

    global $max_upload_limit;
    $ini_value = let_to_num(ini_get('upload_max_filesize'));
    if (!isset($max_upload_limit)) {
        $max_upload_limit == 0;
    }
    if ($max_upload_limit == 0) {
        return $ini_value;
    }
    $max_upload_limit = 1024 * 1024 * $max_upload_limit;
    if ($max_upload_limit < $ini_value) {
        return $max_upload_limit;
    } else {
        return $ini_value;
    }
}

/*!
  \brief  To be used instead of PHP's own readfile function to read
          very large files. This should prevent memory errors.
  \param  $filename  Name of the file to read
  \param  $retbytes  Defines whether the number of bytes read should be returned
                     (default: true)
  \return if reading was not successful, the function returns false. If reading
          was successful, the function returns the number of read bytes if
          $retbytes is true, and a boolean otherwise (true if the file was
          closed successfully, false otherwise).
  \see    http://nl.php.net/manual/en/function.readfile.php#54295
 */
function readfile_chunked($filename, $retbytes=true) {
    $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
    $buffer = '';
    $cnt = 0;
    $handle = fopen($filename, 'rb');
    if ($handle === false) {
        return false;
    }
    while (!feof($handle)) {
        $buffer = fread($handle, $chunksize);
        echo $buffer;
        ob_flush();
        flush();
        if ($retbytes) {
            $cnt += strlen($buffer);
        }
    }
    $status = fclose($handle);
    if ($retbytes && $status) {
        return $cnt; // return num. bytes delivered like readfile() does.
    }
    return $status;
}

if (!function_exists('printDebug')) {

    /* !
      \brief  A global debugging function, that will print all its arguments
              whether are strings, arrays or objects. This works if a global
              variable $debug = true, that can be defined in
              hrm_client_config.inc. Otherwise it does nothing.
     */
    function printDebug() {
        global $debug;

        if (!$debug)
            return;

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

/*!
  \brief  Check whether the browser is Internet Explorer
  \return true if the browser is IE, false otherwise
*/
function using_IE() {
    if (preg_match('/MSIE/i', $_SERVER['HTTP_USER_AGENT'])) {
        return True;
    }
    return False;
}

/*!
  \brief  Check whether the browser is Internet Explorer older than version 9
  \return true if the browser is IE <= 9, false otherwise
*/
function using_IE_lt9() {
    if (preg_match('/MSIE [5-8]/i', $_SERVER['HTTP_USER_AGENT'])) {
        return True;
    }
    return False;
}

?>
