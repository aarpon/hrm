<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

// TODO:
// - block web frontend with an overlay to signalize upload/download

require_once( "User.inc.php" );
require_once( "Fileserver.inc.php" );


// simple wrapper function to unify log messages from this module:
function omelog($text, $level=0) {
    report("OMERO connector: " . $text, $level);
}


class OmeroConnection {

    private $omeroTree; //!< The contents of the user's OMERO tree.

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

    /*! \brief  Get the last requested JSON version of the user's OMERO tree.
        \return The string with the JSON information.
     */
    public function getLastOmeroTree() {
        if (!isset($this->omeroTree)) {
            $this->getUpdatedOmeroTree();
        }

        return $this->omeroTree;
    }

    /*! \brief   Get the sub-tree of a given node.
        \param   $id - The id string of the node, e.g. 'Project:23'
        \param   $levels - The number of sub-levels to retrieve.
        \return  JSON string with the sub-tree.
     */
    public function getSubTree($id, $levels) {
        $param = array('--id', $id, '--levels', $levels);
        $cmd = $this->buildCmd("retrieveSubTree", $param);
        $omeroData = shell_exec($cmd);
        return $omeroData;
    }

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

    /*! \brief   Retrieve the OMERO data tree from the connector script.
        \return  JSON string with the OMERO data tree.
     */
    public function getUpdatedOmeroTree() {
        $cmd = $this->buildCmd("retrieveUserTree");
        $omeroData = shell_exec($cmd);
        if ($omeroData == NULL) {
            $this->omeroTree = NULL;
            $msg = "retrieving OMERO tree data failed!";
            omelog($msg, 1);
            return $msg;
        }

        $this->omeroTree = $omeroData;

        return $this->omeroTree;

    }


    /* ------------------------- Parsers ------------------------------ */

    /*! \brief   Create a string summarizing the HRM job parameters.
        \param   $file - The path and file name of the HRM deconvolution result.
        \return  The plain string with the parameter summary.

        Parse the HRM job parameters (html) file and generate a plain string
        with all the details to be used as OMERO annotation.
     */
    private function getDeconParameterSummary($file) {
        /* A summary title. */
        $summary  = "'[Report of deconvolution parameters from the ";
        $summary .= "Huygens Remote Manager for file ";
        $summary .= basename($file) . " ]: ";

        /* Get the parameter summary (HTML text) of the HRM job. */
        $extension      = pathinfo($file, PATHINFO_EXTENSION);
        $parametersFile = str_replace($extension,"parameters.txt",$file);
        $parameters     = file_get_contents($parametersFile);

        if (!$parameters) {
            $summary .= "Parameters not available.'";
            return $summary;
        }

        /* Loop over the parameter tables. */
        $parameterSets = explode("<table>",$parameters);
        foreach ($parameterSets as $key => $parameterSet) {

            /* Irrelevant information. */
            if ($key == 0) {
                continue;
            }

            /* Loop over the table rows. */
            $rows = explode("<tr>",$parameterSet);
            foreach ($rows as $key => $row) {

                /* Filter out irrelevant information. */
                if ($key == 1 || $key == 2) {
                    continue;
                }

                /* Loop over the row columns. */
                $columns = explode("<td",$row);
                foreach ($columns as $key => $column) {

                    /* Filter out irrelevant information. */
                    if ($key == 3) {
                        continue;
                    }

                    $column = strip_tags($column);
                    $column = explode(">",$column);

                    if (isset($column[1])) {
                        if ($key == 1) {
                            $summary .=
                                str_replace("(&mu;m)","(mu)",$column[1]);
                        }

                        if ($key == 2) {
                            $summary .=
                                " (ch. " . strtolower($column[1]) . "): ";
                        }

                        if ($key == 4) {
                            $summary .= $column[1] . " | ";
                        }
                    }
                }
            }
        }

        return $summary . "'";
    }


    /*! \brief   Remove the HRM-job suffix to find the original file name.
        \param   The name of the deconvolved dataset.
        \return  The name of the raw dataset.
     */
    private function getOriginalName($file) {
        /* Remove any relative paths that may exist. */
        $file = pathinfo($file, PATHINFO_BASENAME);

        /* Remove the HRM deconvolution suffix and file extension. */
        $replaceThis  = "/_([a-z0-9]{13,13})_hrm\.(.*)$/";
        $replaceWith  = "";
        $originalName = preg_replace($replaceThis,$replaceWith,$file);

        /* In case of error just return the name of the deconvolved file. */
        if ($originalName != NULL) {
            return $originalName;
        } else {
            return $file;
        }
    }

}



?>
