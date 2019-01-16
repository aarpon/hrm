<?php
/**
 * HuygensTemplate
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

/**
 * Static class to interact with HuCore.
 *
 * @package hrm
 */
class HuygensTools
{
    /**
     * Calls a local hucore ($local_huygens_core, as defined in settings) to
     * execute some freeware image processing tools, or report
     * parameters. This local hucore doesn't need to have a license!
     * @param string $tool is the procedure in scripts/hucore.tcl to be executed.
     * @param string $options are extra command line options to send to that script
     * @param string $server (optional) Server where to run the command; if $server is empty, it
     *                will be run on localhost.
     * @param string $hucorePath (optional) Full path to hucore; if $hucorePath is empty, $local_huygens_core
     *                from the settings will be used.
     * @return array|null An array with all stdout lines
     * @throws \Exception if a $remote server is specified with no hucore full path.
     */
    public static function huCoreTools($tool, $options, $server="", $hucorePath="")
    {
        global $hrm_path, $local_huygens_core, $huygens_user;

        // Local or remote server?
        $serverIsLocaHost = False;
        if (strcmp($server, "") == 0 || strcmp(strtolower($server), "localhost") == 0) {
            $serverIsLocaHost = True;
        }

        // Use specified hucore path or fall back to $local_huygens_core?
        if ($serverIsLocaHost == true) {
            if (strcmp($hucorePath, "") == 0) {
                if (!isset($local_huygens_core)) {
                    Log::error("Huygens tools can only work if you define a variable " .
                        "'local_huygens_core' in the configuration files pointing to a local " .
                        "hucore. Administrator: see hrm_client_config.inc.sample.");
                    return null;
                }
                $hucorePathToUse = $local_huygens_core;
            } else {
                $hucorePathToUse = $hucorePath;
            }
        } else {
            if (strcmp($hucorePath, "") == 0) {
                throw new \Exception("A full hucore path must be specified for a remote server!");
            }
            $hucorePathToUse = $hucorePath;
        }

        // Build the core command
        $cmd = "$hucorePathToUse -noExecLog -checkUpdates disable " .
            "-task \"$hrm_path/scripts/hucore.tcl\" " .
            "-huCoreTcl \"$hrm_path/scripts/hucore.tcl\" " .
            "-tool $tool $options 2>&1";

        // If needed, wrap it in an ssh call
        if ($serverIsLocaHost == False) {
            $cmd = 'ssh -f ' . $huygens_user . "@" . $server . " '" . $cmd . " '";
        }

        // Get the proper (local or remote) Processor
        $answer = exec($cmd, $output, $result);

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
                echo("$line\n");
                $more -= 1;
                if ($more == 0)
                    echo("...\n");
            }
            if (stristr($line, "error")) {
                echo("$line\n");
                $more = 2;
            }
        }
        echo "</pre>";
        return NULL;
    }

    /**
     * A wrapper around huCoreTools to retrieve an array, which is
     * 'calculated' by hucore in the background.
     * @param string $tool It is the procedure in scripts/hucore.tcl to be executed.
     * @param string $options These are extra command line options to send to that script
     * hucore output. By default, it uses the same tool name.
     * @param string $server (optional) Server where to run the command; if $server is empty, it
     *                will be run on localhost.
     * @param string $hucorePath (optional) Full path to hucore; if $hucorePath is empty, $local_huygens_core
     *                from the settings will be used.
     * @return array|string The requested array.
     * @todo Do not return different types!
     */
    public static function askHuCore($tool, $options = "", $server="", $hucorePath="")
    {

        try {
            $answer = self::huCoreTools($tool, $options, $server, $hucorePath);
        } catch (\Exception $e) {
            $answer = null;
        }

        if (!$answer)
            return null;

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

}