<?php
/**
 * OmeroConnection
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

/**
 * Handles communication to an Omero server.
 *
 * @package hrm
 */
class OmeroConnection
{

    /** The OMERO username for authentication + logging.
     * @var string
     */
    private $omeroUser;

    /**
     * The OMERO user password.
     * @var string
     */
    private $omeroPass;

    /**
     * Boolean to know if the login was successful.
     * @var bool
     */
    public $loggedIn;

    /**
     * OMERO connector executable.
     * @var string
     */
    private $omeroWrapper = "bin/ome_hrm.py";

    /**
     * Array map to hold children in JSON format.
     *
     * Associative array that is used to cache children in JSON strings so
     * they don't have to be re-requested from the OMERO server. The key to
     * access entries is of the form 'OMERO_CLASS:int', e.g. 'Dataset:23'.
     *
     * @var array
     */
    private $nodeChildren = array();


    /**
     * OmeroConnection constructor.
     * @param string $omeroUser User name for the OMERO server.
     * @param string $omeroPass Password for the OMERO server.
     */
    public function __construct($omeroUser, $omeroPass)
    {
        if (empty($omeroUser)) {
            $this->omelog("No OMERO user name provided, cannot login.", 0);
            return;
        }

        if (empty($omeroPass)) {
            $this->omelog("No OMERO password provided, cannot login.", 0);
            return;
        }

        $this->omeroUser = $omeroUser;
        $this->omeroPass = $omeroPass;

        $this->checkOmeroCredentials();
        if ($this->loggedIn == TRUE) {
            $this->omelog("Successfully connected to OMERO!", 2);
        } else {
            $this->omelog("ERROR connecting to OMERO!", 0);
        }
    }


    /* -------------------- General OMERO processes -------------------- */

    /**
     * Check to authenticate to OMERO using given credentials.
     *
     * Try to establish communication with the OMERO server using the login
     * credentials provided by the user.
     *
     * This function returns nothing, sets the $this->loggedIn class variable.
     */
    private function checkOmeroCredentials()
    {
        $this->omelog("attempting to log on to OMERO.", 2);
        $cmd = $this->buildCmd("checkCredentials");

        /* Authenticate against the OMERO server. */
        exec($cmd, $out, $retval);

        /* $retval is zero in case of success */
        if ($retval != 0) {
            $this->loggedIn = FALSE;
            $this->omelog("ERROR: checkCredentials(): " . implode(' ', $out), 0);
            return;
        } else {
            $this->loggedIn = TRUE;
        }
    }

    /**
     * Retrieve selected images from the OMERO server.
     * @param string $images JSON object with IDs and names of selected images.
     * @param Fileserver $fileServer Instance of the Fileserver class.
     * @return string A human readable string reporting success and failed images.
     */
    public function downloadFromOMERO($images, Fileserver $fileServer)
    {
        $selected = json_decode($images, true);
        $fail = "";
        $done = "";
        foreach ($selected as $img) {
            $fileAndPath = $fileServer->sourceFolder() . "/" . $img['name'];
            $param = array("--imageid", $img['id'], "--dest", $fileAndPath);
            $cmd = $this->buildCmd("OMEROtoHRM", $param);

            $this->omelog('requesting [' . $img['id'] . '] to [' . $fileAndPath . ']', 2);
            // somehow exec() seems to append to $out instead of overwriting
            // it, so we create an empty array for it explicitly:
            $out = array();
            exec($cmd, $out, $retval);
            $this->omelog(implode(' ', $out), 2);
            if ($retval != 0) {
                $this->omelog("failed retrieving [" . $img['id'] ."] from OMERO", 0);
                $this->omelog("ERROR: downloadFromOMERO(): " . implode(' ', $out), 0);
                $fail .= "<br/>" . $img['id'] . "&nbsp;&nbsp;&nbsp;&nbsp;";
                $fail .= "[" . implode(' ', $out) . "]<br/>";
            } else {
                $this->omelog("success retrieving [" . $img['id'] . "] from OMERO", 2);
                $done .= "<br/>" . implode('<br/>', $out) . "<br/>";
            }
        }
        // build the return message:
        $msg = "";
        if ($done != "") {
            // @todo Use CSS
            $msg .= "<font color='green'>";
            $msg .= "Successfully retrieved from OMERO:<br/>" . $done;
            $msg .= "</font>";
        }
        if ($fail != "") {
            $msg .= "<font color='red'>";
            $msg .= "<br/><br/>FAILED retrieving from OMERO:<br/>" . $fail;
            $msg .= "</font>";
        }
        return $msg;
    }

    /**
     * Attach a deconvolved image to an OMERO dataset.
     * @param array $postedParams An alias of $_POST with names of selected files.
     * @param Fileserver $fileServer An instance of the Fileserver class.
     * @return string A human readable string reporting success and failed images.
     */
    public function uploadToOMERO(array $postedParams, Fileserver $fileServer)
    {
        $selectedFiles = json_decode($postedParams['selectedFiles']);

        if (sizeof($selectedFiles) < 1) {
            $msg = "No files selected for upload.";
            $this->omelog($msg, 0);
            return $msg;
        }

        if (!isset($postedParams['OmeDatasetId'])) {
            $msg = "No destination dataset selected.";
            $this->omelog($msg, 0);
            return $msg;
        }

        $datasetId = $postedParams['OmeDatasetId'];

        /* Export all the selected files. */
        $fail = "";
        $done = "";
        foreach ($selectedFiles as $file) {
            // TODO: check if $file may contain relative paths!
            $fileAndPath = $fileServer->destinationFolder() . "/" . $file;
            $param = array("--file", $fileAndPath, "--dset", $datasetId);
            $cmd = $this->buildCmd("HRMtoOMERO", $param);

            $this->omelog('uploading [' . $fileAndPath . '] to dataset ' . $datasetId, 2);
            // somehow exec() seems to append to $out instead of overwriting
            // it, so we create an empty array for it explicitly:
            $out = array();
            exec($cmd, $out, $retval);
            if ($retval != 0) {
                $this->omelog("failed uploading file to OMERO: " . $file, 0);
                $this->omelog("ERROR: uploadToOMERO(): " . implode(' ', $out), 0);
                $fail .= "<br/>" . $file . "&nbsp;&nbsp;&nbsp;&nbsp;";
                $fail .= "[" . implode(' ', $out) . "]<br/>";
            } else {
                $this->omelog("success uploading [" . $file . "] to OMERO.", 2);
                $done .= "<br/>" . $file;
            }
        }
        // reload the OMERO tree:
        $this->resetNodes();
        // build the return message:
        $msg = "";
        if ($done != "") {
            $msg .= "<font color='green'>";
            $msg .= "Successfully uploaded to OMERO:<br/>" . $done . "<br/>";
            $msg .= "</font>";
        }
        if ($fail != "") {
            $msg .= "<font color='red'>";
            $msg .= "<br/><br/>FAILED uploading to OMERO:<br/>" . $fail;
            $msg .= "</font>";
        }
        return $msg;
    }


    /* ---------------------- Command builder --------------------------- */

    /**
     * Generic command builder for the OMERO connector.
     *
     * This is the generic command builder that is called by the various
     * functions using the connector executable and takes care of all the
     * common tasks that are independent of the specific command, like adding
     * the credentials, making sure all parameters are properly quoted, etc.
     *
     * @param string $command The command to be run by the wrapper.
     * @param array $parameters (optional) An array of additional parameters
     * required by the wrapper to run the requested command.
     * @return string  A string with the complete command.
     */
    private function buildCmd($command, array $parameters = array())
    {
        // escape all shell arguments
        foreach ($parameters as &$param) {
            $param = escapeshellarg($param);
        }
        // build a temporary array with the command elements, starting with the
        // connector/wrapper itself:
        $tmp = array($this->omeroWrapper);
        //// $tmp = array("/usr/bin/python");
        //// array_push($tmp, $this->omeroWrapper);
        // user/password must be given first:
        array_push($tmp, "--user", escapeshellarg($this->omeroUser));
        array_push($tmp, "--password", escapeshellarg($this->omeroPass));
        // next the *actual* command:
        array_push($tmp, escapeshellarg($command));
        // and finally the parameters (if any):
        $tmp = array_merge($tmp, $parameters);
        // now we can assemble the full command string:
        $cmd = join(" ", $tmp);
        // and and intermediate one for logging w/o password:
        $tmp[4] = '"$OMEROPW"';
        $this->omelog("> " . join(" ", $tmp), 2);
        return $cmd;
    }


    /* ---------------------- OMERO Tree Assemblers ------------------- */

    /**
     * Get the children of a given node.
     * @param  string $id The id string of the node, e.g. 'Project:23'
     * @return string JSON string with the child-nodes.
     */
    public function getChildren($id)
    {
        if (!isset($this->nodeChildren[$id])) {
            $param = array('--id', $id);
            $cmd = $this->buildCmd("retrieveChildren", $param);
            exec($cmd, $out, $retval);
            if ($retval != 0) {
                $this->omelog("ERROR: getChildren(): " . implode(' ', $out), 0);
                return FALSE;
            } else {
                $this->nodeChildren[$id] = implode(' ', $out);
            }
        }
        return $this->nodeChildren[$id];
    }

    /**
     * Reset the array keeping the node data.
     *
     * This is useful to refresh the tree, as all calls to getChildren() will
     * then request up-to-date information from OMERO.
     */
    public function resetNodes()
    {
        $this->nodeChildren = array();
    }

    /**
     * Simple wrapper function to unify log messages from this module.
     * @param string $text Text to be logged
     * @param int $level Severity level, 0=error, 1=warning (default), 2=info.
     * @todo Is this wrapper necessary?
     */
    private function omelog($text, $level = 0)
    {
        $message = "[HRM-OMERO] " . $text;
        // @todo Use class Log
        switch ($level) {
            case 0:
                Log::error($message);
                break;
            case 1:
                Log::warning($message);
                break;
            case 2:
                Log::info($message);
                break;
            default:
                Log::warning($message);
                break;
        }
    }

}
