<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

require_once( "User.inc.php" );
require_once( "Fileserver.inc.php" );


class OmeroConnection {

    /*!
      \var    $omeroTree
      \brief  The contents of the user's OMERO tree.
    */
    private $omeroTree;

    /*!
      \var    $omeroUser
      \brief  The OMERO user name for authentication and logging purposes.
    */
    private $omeroUser;

    /*!
      \var    $omeroPass
      \brief  The OMERO user password for authentication and logging purposes.
    */
    private $omeroPass;

   /*!
      \var    $loggedIn
      \brief  Boolean to know whether the login was successful.
    */
    public $loggedIn;

   /*!
      \var    $omeroWrapper
      \brief  The shell wrapper to OMERO's command line tool.
    */
    private $omeroWrapper = "bin/ome_hrm";


        /* ----------------------- Constructor ---------------------------- */

    /*!
     \brief   Constructor
    */
    public function __construct( $omeroUser, $omeroPass ) {

        if (empty($omeroUser)) {
            report("No OMERO user name provided, cannot login.", 2);
            return;
        }

        if (empty($omeroPass)) {
            report("No OMERO password provided, cannot login.", 2);
            return;
        }

        $this->omeroUser = $omeroUser;
        $this->omeroPass = $omeroPass;

        $this->checkOmeroCredentials();
    }

        /* -------------------- General OMERO processes -------------------- */

    /*!
     \brief   Try to establish communication with the OMERO server using the
              login credentials provided by the user.
    */
    private function checkOmeroCredentials() {

        report("Attempting to log on to OMERO, user=[" . $this->omeroUser .
               "], password=[********].", 2);
        $cmd = $this->buildCredentialsCmd();

            /* Authenticate against the OMERO server. */
        $loggedIn = shell_exec($cmd);

            /* Returns NULL if an error occurred or no output was produced. */
        if ($loggedIn == NULL) {
            report("ERROR logging on to OMERO.", 0);
            return;
        }

            /* Check whether the attempt was successful. */
        if (strstr($loggedIn, '-1')) {
            $this->loggedIn = FALSE;
            report("Attempt to log on to OMERO server failed.", 1);
        } else {
            $this->loggedIn = TRUE;
        }
    }

    /*!
     \brief   Retrieve the OMERO data tree as returned by the ome_hrm script.
     \return  The XML string with the OMERO data tree.
    */
    private function getRawOmeroDataTree () {

        $cmd = $this->buildTreeCmd();

        $omeroData = shell_exec($cmd);
        if ($omeroData == NULL) {
            report("Retrieving OMERO data failed.", 1);
            return "Retrieving OMERO data failed.";
        }

        return $omeroData;
    }

    /*!
     \brief   Retrieve one image from the OMERO server.
     \param   $postedParams Alias of $_POST with the user selection.
     \param   $fileServer Instance of the Fileserver class.
     \return  Ocassionally, an error message.
     \todo    Should we return "true" in case of success?
    */
    public function importImage($postedParams, $fileServer) {

        if (isset($postedParams['OmeImageName'])) {
            $imgName = basename($postedParams['OmeImageName']);
            $imgName = str_replace("Image: ","",$imgName);
        } else {
            return "No files selected.";
        }

        if (isset($postedParams['OmeImageId'])) {
            $imgId = $postedParams['OmeImageId'];
        } else {
            return "No files selected.";
        }

        $cmd = $this->buildImportCmd($imgName, $fileServer, $imgId);

        if (shell_exec($cmd) == NULL) {
            report("Importing image from OMERO failed.", 1);
            return "Importing image from OMERO failed.";
        }
    }

    /*!
     \brief   Attach a deconvolved image to an OMERO dataset.
     \param   $postedParams An alias of $_POST with names of selected files.
     \param   $fileServer   An instance of the Fileserver class.
     \return  Ocassionally an error message.
     \todo    Should we return "true" in case of success?
    */
    public function exportImage($postedParams, $fileServer) {

        if (isset($postedParams['selectedFiles'])) {
            $selectedFiles = explode(" ",trim($postedParams['selectedFiles']));
        } else {
            return "No files selected.";
        }

        if (isset($postedParams['OmeDatasetId'])) {
            $datasetId = $postedParams['OmeDatasetId'];
        } else {
            return "No destination dataset selected.";
        }

            /* Export all the selected files. */
        foreach ($selectedFiles as $file) {

            $cmd = $this->buildExportCmd($file, $fileServer, $datasetId);

            if (shell_exec($cmd) == NULL) {
                report("Exporting image to OMERO failed.", 1);
                return "Exporting image to OMERO failed.";
            }
        }
    }

        /* ---------------------- Command builders--------------------------- */

    /*!
     \brief   Generic command builder for the OMERO wrapper script, adding the
              credentials and making sure all parameters are properly quoted.
     \param   $command - The command to be run by the wrapper.
     \param   $parameters (optional) - An array of additional parameters
              required by the wrapper to run the requested command.
     \return  A string with the complete command.
    */
    private function buildCmd($command, $parameters="") {
        // escape all shell arguments
        foreach($parameters as &$param) {
            $param = escapeshellarg($param);
        }
        // now we assemble the full shell command
        $cmd  = $this->omeroWrapper . " ";
        $cmd .= $command . " ";
        $cmd .= escapeshellarg($this->omeroUser) . " ";
        $cmd .= escapeshellarg($this->omeroPass) . " ";
        $cmd .= join(" ", $parameters);
        return $cmd;
    }

    /*!
     \brief   Build the command to check whether the user can log on to OMERO.
     \return  A string with the complete command.
    */
    private function buildCredentialsCmd() {
        return $this->buildCmd("checkCredentials");
    }

    /*!
     \brief   Build the command to retrieve the user's OMERO data tree.
     \return  A string with the complete command.
    */
    private function buildTreeCmd() {
        return $this->buildCmd("retrieveUserTree");
    }

    /*!
     \brief   Build the command to export one image to the OMERO server.
     \param   $file - The name and relative path of the image file.
     \param   $fileServer - An instance of the Fileserver class.
     \param   $datasetId - The OMERO ID of the dataset to export the image to.
     \return  A string with the complete command.
    */
    private function buildExportCmd($file, $fileServer, $datasetId) {
        // FIXME: previous documentation said "$file may contain relative
        // paths" - is this always true? Otherwise this method of constructing
        // the absolute path will fail!
        $fileAndPath = $fileServer->destinationFolder() . "/" . $file;
        return $this->buildCmd("HRMtoOMERO",
            array($datasetId, $fileAndPath,
                  $this->getOriginalName($file),
                  $this->getDeconParameterSummary($fileAndPath)));
    }

    /*!
     \brief   Build the command to import one image from the OMERO server.
     \param   $imgName - The name of the image in the OMERO server.
     \param   $fileServer - An instance of the Fileserver class.
     \param   $imgId - The ID of the image in the OMERO server.
     \return  A string with the complete command.
    */
    private function buildImportCmd($imgName, $fileServer, $imgId) {
        // FIXME: previous documentation said "$file may contain relative
        // paths" - is this always true? Otherwise this method of constructing
        // the absolute path will fail!
        $fileAndPath = $fileServer->sourceFolder() . "/" . $imgName;
        return $this->buildCmd("OMEROtoHRM ", array($imgId, $fileAndPath));
    }

        /* ---------------------- OMERO Tree Assemblers ------------------- */

    /*!
     \brief  Get the last requested JSON version of the user's OMERO tree.
     \return The string with the JSON information.
    */
    public function getLastOmeroTree() {

        if (!isset($this->omeroTree)) {
            $this->getUpdatedOmeroTree();
        }

        return $this->omeroTree;
    }

    /*!
     \brief  Get an updated JSON version of the user's OMERO tree.
     \return The string with the JSON information.
    */
    public function getUpdatedOmeroTree() {

        $this->omeroTree = $this->getRawOmeroDataTree();

        return $this->omeroTree;
    }


        /* ------------------------- Parsers ------------------------------ */
    /*!
     \brief   Parse the HRM job parameters (html) file into a plain string to
              be used as OMERO annotation.
     \param   $file The path and file name of the HRM deconvolution result.
     \return  The plain string with the parameter summary.
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

                    /* Irrelevant information. */
                if ($key == 1 || $key == 2) {
                    continue;
                }

                    /* Loop over the row columns. */
                $columns = explode("<td",$row);
                foreach ($columns as $key => $column) {

                        /* Irrelevant information. */
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

    /*!
     \brief   Remove the deconvolution suffix to find the original file name.
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
