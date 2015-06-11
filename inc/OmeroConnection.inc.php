<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

require_once( "User.inc.php" );
require_once( "Fileserver.inc.php" );


// simple wrapper function to unify log messages from this module:
function omelog($text, $level=0) {
    report("OMERO connector: " . $text, $level);
}


class OmeroConnection {

    private $omeroUser; //!< The OMERO username for authentication + logging.

    private $omeroPass; //!< The OMERO user password.

    public $loggedIn;   //!< Boolean to know if the login was successful.

    private $omeroWrapper = "bin/ome_hrm.py"; //!< OMERO connector executable.

    /*! \brief Array map to hold children in JSON format.
        \var   $nodeChildren

        Associative array that is used to cache children in JSON strings so
        they don't have to be re-requested from the OMERO server. The key to
        access entries is of the form 'OMERO_CLASS:int', e.g. 'Dataset:23'.
     */
    private $nodeChildren = array();


    /* ----------------------- Constructor ---------------------------- */
    public function __construct( $omeroUser, $omeroPass ) {
        if (empty($omeroUser)) {
            omelog("No OMERO user name provided, cannot login.", 2);
            return;
        }

        if (empty($omeroPass)) {
            omelog("No OMERO password provided, cannot login.", 2);
            return;
        }

        $this->omeroUser = $omeroUser;
        $this->omeroPass = $omeroPass;

        $this->checkOmeroCredentials();
        omelog("Successfully connected to OMERO!", 2);
    }


    /* -------------------- General OMERO processes -------------------- */

    /*! \brief  Check to authenticate to OMERO using given credentials.
        \return Noting, sets the $this->loggedIn class variable.

        Try to establish communication with the OMERO server using the login
        credentials provided by the user.
     */
    private function checkOmeroCredentials() {
        omelog("attempting to log on to OMERO.", 2);
        $cmd = $this->buildCmd("checkCredentials");

            /* Authenticate against the OMERO server. */
        $loggedIn = shell_exec($cmd);

            /* Returns NULL if an error occurred or no output was produced. */
        if ($loggedIn == NULL) {
            omelog("ERROR logging on to OMERO.", 0);
            return;
        }

            /* Check whether the attempt was successful. */
        if (strstr($loggedIn, '-1')) {
            $this->loggedIn = FALSE;
            omelog("Attempt to log on to OMERO server failed.", 0);
        } else {
            $this->loggedIn = TRUE;
        }
    }

    /*! \brief   Retrieve one image from the OMERO server.
        \param   $postedParams Alias of $_POST with the user selection.
        \param   $fileServer Instance of the Fileserver class.
        \return  Ocassionally, an error message.
        \todo    Should we return "true" in case of success?
     */
    public function downloadFromOMERO($postedParams, $fileServer) {
        if (isset($postedParams['OmeImageName'])) {
            $imgName = $postedParams['OmeImageName'];
        } else {
            return "No files selected.";
        }

        if (isset($postedParams['OmeImageId'])) {
            $imgId = $postedParams['OmeImageId'];
        } else {
            return "No files selected.";
        }

        $fileAndPath = $fileServer->sourceFolder() . "/" . $imgName;
        $param = array("--imageid", $imgId, "--dest", $fileAndPath);
        $cmd = $this->buildCmd("OMEROtoHRM", $param);

        omelog('requesting ' . $imgId . ' to ' . $fileAndPath);
        exec($cmd, $out, $retval);
        if ($retval != 0) {
            $msg = "failed retrieving " . $imgId;
            omelog($msg, 1);
            return $msg;
        }
        omelog("successfully retrieved " . $imgId, 1);
        return "Successfully retrieved " . $imgId . "!";
    }

    /*! \brief   Attach a deconvolved image to an OMERO dataset.
        \param   $postedParams An alias of $_POST with names of selected files.
        \param   $fileServer   An instance of the Fileserver class.
        \return  Ocassionally an error message.
        \todo    Should we return "true" in case of success?
     */
    public function uploadToOMERO($postedParams, $fileServer) {
        $selectedFiles = json_decode($postedParams['selectedFiles']);

        if (sizeof($selectedFiles) < 1) {
            $msg = "No files selected for upload.";
            omelog($msg);
            return $msg;
        }

        if (! isset($postedParams['OmeDatasetId'])) {
            $msg = "No destination dataset selected.";
            omelog($msg);
            return $msg;
        }

        $datasetId = $postedParams['OmeDatasetId'];

        /* Export all the selected files. */
        foreach ($selectedFiles as $file) {
            // TODO: check if $file may contain relative paths!
            $fileAndPath = $fileServer->destinationFolder() . "/" . $file;
            $param = array("--file", $fileAndPath, "--dset", $datasetId);
            $cmd = $this->buildCmd("HRMtoOMERO", $param);

            omelog('uploading "' . $fileAndPath . '" to dataset ' . $datasetId);
            if (shell_exec($cmd) == NULL) {
                $msg = "exporting image to OMERO failed.";
                omelog($msg, 1);
                return $msg;
            }
            omelog("successfully uploaded " . $file, 1);
            return "Successfully uploaded " . $file . "!";
        }
    }


    /* ---------------------- Command builder --------------------------- */

    /*! \brief   Generic command builder for the OMERO connector.
        \param   $command - The command to be run by the wrapper.
        \param   $parameters (optional) - An array of additional parameters
                 required by the wrapper to run the requested command.
        \return  A string with the complete command.

        This is the generic command builder that is called by the various
        functions using the connector executable and takes care of all the
        common tasks that are independent of the specific command, like adding
        the credentials, making sure all parameters are properly quoted, etc.
     */
    private function buildCmd($command, $parameters=array()) {
        // escape all shell arguments
        foreach($parameters as &$param) {
            $param = escapeshellarg($param);
        }
        // build a temporary array with the command elements, starting with the
        // connector/wrapper itself:
        $tmp = array($this->omeroWrapper);
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
        $tmp[4] = "[********]";
        omelog("> " . join(" ", $tmp), 1);
        return $cmd;
    }


    /* ---------------------- OMERO Tree Assemblers ------------------- */

    /*! \brief   Get the children of a given node.
        \param   $id - The id string of the node, e.g. 'Project:23'
        \return  JSON string with the child-nodes.
     */
    public function getChildren($id) {
        if (!isset($this->nodeChildren[$id])) {
            $param = array('--id', $id);
            $cmd = $this->buildCmd("retrieveChildren", $param);
            $this->nodeChildren[$id] = shell_exec($cmd);
        }
        return $this->nodeChildren[$id];
    }

    /*! \brief   Reset the array keeping the node data.

        This is useful to refresh the tree, as all calls to getChildren() will
        then request up-to-date information from OMERO.
     */
    public function resetNodes() {
        $this->nodeChildren = array();
    }

}

?>
