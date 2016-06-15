<?php
/**
 * Util
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Static class with some commodity functionality.
 */
class Util
{

    /**
     * Map of integer to character.
     * @var array
     */
    private static $s2iMap = array(
        "1"  => "a",
        "2"  => "b",
        "3"  => "c",
        "4"  => "d",
        "5"  => "e",
        "6"  => "f",
        "7"  => "g",
        "8"  => "h",
        "9"  => "i",
        "10" => "j",
        "11" => "k",
        "12" => "l",
        "13" => "m",
        "14" => "n",
        "15" => "o",
        "16" => "p",
        "17" => "q",
        "18" => "r",
        "19" => "s",
        "20" => "t",
        "21" => "u",
        "22" => "v",
        "23" => "w",
        "24" => "x",
        "25" => "y",
        "26" => "z",
        "27" => "0",
        "28" => "1",
        "29" => "2",
        "30" => "3",
        "31" => "4",
        "32" => "5",
        "33" => "6",
        "34" => "7",
        "35" => "8",
        "36" => "9");


    /**
     * Escapes html strings generated in PHP so that they can be used as
     * arguments in JavaScript functions.
     * @param string $str An html string.
     * @return string Conveniently formatted string.
     */
    public static function escapeJavaScript($str)
    {
        $str = str_replace("'", "\'", $str);
        $str = str_replace("\n", "\\n", $str);
        $str = str_replace('"', "'+String.fromCharCode(34)+'", $str);
        return $str;
    }

    /**
     * Returns a random string to be used as id.
     * @param int $length Length of the string.
     * @return string A a random string of requested length.
     * @todo There are better ways to do this!
     */
    public static function get_rand_id($length)
    {
        $rand_id = "";
        if ($length > 0) {
            $rand_id = "";
            for ($i = 1; $i <= $length; $i++) {
                mt_srand((double)microtime() * 1000000);
                $num = mt_rand(1, 36);
                $rand_id .= self::$s2iMap[$num];
            }
        }
        return $rand_id;
    }

    /**
     * Returns the name of current page
     * @return string Name of current page.
     */
    public static function getThisPageName()
    {
        return substr($_SERVER["SCRIPT_NAME"],
            strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);
    }

    /**
     * Alternative to the PHP function 'array_search' to find out whether a
     * particular value exists in an array. PHP functions mistake '0' for ''.
     * @param array $array The array where to search.
     * @param mixed $value The value to look for
     * @return bool True if the value exists in the array, false otherwise.
     * @todo Is this really necessary?
     */
    public static function isValueInArray($array, $value)
    {

        if (!is_array($array)) {
            return false;
        }

        $found = FALSE;

        foreach ($array as $arrKey => $arrValue) {

            /* A first filter on the length to distinguish '0' and '', which
             apparently cannot be told apart with '===' */
            if (strlen($arrValue) == strlen($value)) {
                if ($arrValue == $value) {
                    $found = TRUE;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Sends an email to the admin to notify any message. It helps in
     * debugging strange events, and in being aware of abnormal scheduler
     * stops. The message is also automatically reported to log.
     * @param string $subject A string to be appended to the email subject.
     * @param string $message The body of the email.
     */
    public static function notifyRuntimeError($subject, $message)
    {
        global $email_sender;
        global $email_admin;
        $mail = new Mail($email_sender);
        $mail->setReceiver($email_admin);
        $mail->setSubject('Huygens Remote Manager - ' . $subject);
        $mail->setMessage($message);
        $mail->send();
        # No need to report to log, send() already did.
    }

    /**
     * Chunked read file. To be used instead of PHP's own readfile function to read
     * very large files. This should prevent memory errors.
     * @param string $filename Name of the file to read.
     * @param bool $retbytes Defines whether the number of bytes read should be returned
     * (default: true)
     * @return bool|int If reading was not successful, the function returns false.
     * If reading was successful, the function returns the number of read bytes if
     * $retbytes is true, and a boolean otherwise (true if the file was closed
     * successfully, false otherwise).
     * @see http://nl.php.net/manual/en/function.readfile.php#54295
     */
    public static function readfile_chunked($filename, $retbytes = true)
    {
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

    /**
     * Check whether the browser is Internet Explorer.
     * @return bool True if the browser is IE, false otherwise.
     */
    public static function using_IE()
    {
        if (preg_match('/MSIE/i', $_SERVER['HTTP_USER_AGENT'])) {
            return True;
        }
        return False;
    }

}
