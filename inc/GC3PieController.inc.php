<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

require_once( "User.inc.php" );
require_once( "JobDescription.inc.php" );
require_once( "Fileserver.inc.php" );

class GC3PieController {

    /*!
     \brief $controller
     \var   String containing information for the GC3Pie job.
    */
    public $controller;

    /*!
     \var    $jobDescription
     \brief  JobDescription object.
    */
    private $jobDescription;

    /*!
     \brief $sectionsArray
     \var   Array with the main GC3Pie fields.
    */
    private $sectionsArray;

    /*!
     \brief $hrmJobFileArray
     \var   Array with fields for the HRM section.
    */
    private $hrmJobFileArray;

    /*!
     \brief $hrmJobFileList 
     \var   HRM section of the controller sorted for GC3Pie.
    */
    private $hrmJobFileList;

    /*!
     \brief $hucoreArray
     \var   Array with fields for hucore tasks.
    */
    private $hucoreArray;

    /*!
     \brief $hucoreList
     \var   Section where to specify details about HuCore.
    */
    private $hucoreList;

    /*!
     \brief $idList
     \var   Section where to specify job ids.
    */
    private $idList;

    /*!
     \brief $inputFilesArray;
     \var   Array with fields for the input file section.
    */
    private $inputFilesArray;

    /*!
     \brief $inputFilesList 
     \var   Input file section sorted properly for GC3Pie.
    */
    private $inputFilesList;


    /* ------------------------------------------------------------------------ */
    
    /*!
     \brief  Constructor
     \param  $jobDescription JobDescription object
    */
    public function __construct( $jobDescription ) {
        $this->jobDescription  = $jobDescription;
        $this->initializeSections();
        $this->setHrmJobFileSectionList();
        $this->setHuCoreSectionList();
        $this->setInputFilesSectionList();
        $this->setDeleteJobsSectionList();
        $this->assembleController();
    }


    /* ----------------------- Initialization ---------------------------- */
    /*!
     \brief  Sets general class properties to initial values.
    */
    private function initializeSections() {
        
        $this->sectionsArray = array (
            'hrmjobfile'  ,
            'hucore',
            'deletejobs',
            'inputfiles'
        );        
        
        $this->hrmJobFileArray = array (
            'version'       =>  '5',
            'username'      =>  '',
            'useremail'     =>  '',
            'jobtype'       =>  '',
            'timestamp'     =>  ''
        );
        
        $this->deleteJobsArray = array (
            'ids'           =>  ''
        );

        $this->hucoreArray = array (
            'executable'    =>   '',
            'template'      =>   ''
        );
        
        $this->inputFilesArray = array (
            'file'          =>   ''
        );
        
    }
    
    
    /* ----------------------------------------------------------------- */
    
    /*!
     \brief  Sets the HRM job file section field.
    */
    private function setHrmJobFileSectionList() {
        $this->hrmJobFileList = "";
        
        $user = $this->jobDescription->owner();
        
        foreach ($this->hrmJobFileArray as $key => $value) {
            $this->hrmJobFileList .= $key;
            switch ( $key ) {
            case "version":
                $this->hrmJobFileList .= " = " . $value;
                break;
            case "username":
                $this->hrmJobFileList .= " = ";
                $this->hrmJobFileList .= $user->name();
                break;
            case "useremail":
                $this->hrmJobFileList .= " = ";
                $this->hrmJobFileList .= $user->emailAddress();
                break;
            case "jobtype":
                $this->hrmJobFileList .= " = ";
                $this->hrmJobFileList .= $this->jobDescription->getJobType();
                break;
            case "id":
                $this->hrmJobFileList .=  " = " . $this->jobDescription->getJobID();
                break;
            case "timestamp":
                $this->hrmJobFileList .= " = " . microtime(true);
                break;
            default:
                error_log("Unimplemented HRM job file section field: $key");
            }
            $this->hrmJobFileList .= "\n";
        }
    }

    /*!
     \brief  Sets the ID  section field.
    */
    private function setDeleteJobsSectionList() {
        if ($this->jobDescription->getJobType() != "deletejobs") {
            return;
        }
        
        $this->idList = "";
        foreach ($this->deleteJobsArray as $key => $value) {
            $this->idList .=  $key;
            
            switch($key) {
            case "ids":
                $this->idList .= " = " . $this->jobDescription->getJobID();
                break;
            default:
                error_log();
            }
            $this->idList .= "\n";
        }
    }

    /*!
     \brief  Sets the hucore section field.
    */
    private function setHuCoreSectionList() {
        global $local_huygens_core;

        if ($this->jobDescription->getJobType() == "deletejobs") {
            return;
        }

        $this->hucoreList = "";
        $templatePath = $this->jobDescription->sourceFolder();
        $templateName = $this->jobDescription->getHuTemplateName();
        foreach ($this->hucoreArray as $key => $value) {
            $this->hucoreList .= $key;
            switch ( $key ) {
                case "executable":
                    if (isset($local_huygens_core)) {
                        $this->hucoreList .= " = " . $local_huygens_core;
                    } else {
                        error_log("Unreachable hucore binary.");
                    }
                    break;
                case "template":
                    $this->hucoreList .= " = ";
                    $this->hucoreList .= $templatePath;
                    $this->hucoreList .= $templateName;
                    break;
                default:
                    error_log("Unimplemented Hucore section field: $key");
            }
            $this->hucoreList .= "\n";
        }
    }

    /*!
     \brief  Sets the input file section field.
    */
    private function setInputFilesSectionList() {

        if ($this->jobDescription->getJobType() == "deletejobs") {
            return;
        }
        
        $numberedFiles = "";

        $fileCnt = 0;
        $filePath   = $this->jobDescription->sourceFolder();
        $inputFiles = $this->jobDescription->files();
        foreach ($inputFiles as $file) {
            $fileCnt++;
            $numberedFiles .= "file" . $fileCnt . " = ";
            $numberedFiles .= $filePath . $file . "\n";
        }

        foreach ($this->inputFilesArray as $key => $value) {
            switch ( $key ) {
                case "file":
                    $this->inputFilesList = $numberedFiles;
                    break;
                default:
                    error_log("Unimplemented input file section field: $key");
            }
        }
    }

    /*!
     \brief  Puts together all the contents of the controller file.
    */
    private function assembleController() {
        $this->controller = "";
        
        foreach ($this->sectionsArray as $section) {

            if ($this->jobDescription->getJobType()  == "deletejobs")  {
                if ($section  == "hucore" || $section == "inputfiles") {
                    continue;
                }
            }

            if ($this->jobDescription->getJobType() !=  "deletejobs") {
                if ($section  == "deletejobs") {
                    continue;
                }
            }
            
            $this->controller .= "[" . $section . "]" . "\n";
            
            switch ($section) {
            case "hrmjobfile":
                $this->controller .= $this->hrmJobFileList;
                break;
            case "hucore":
                $this->controller .= $this->hucoreList;
                break;
            case "deletejobs":
                $this->controller .= $this->idList;
                break;
            case "inputfiles":
                $this->controller .= $this->inputFilesList;
                break;
            default:
                error_log("Unimplemented controller section: $section");
            }
            $this->controller .= "\n";
        }
    }
    
    
    /*!
      \brief	Writes the HRM jobfile to the Queue Mananger's spool folder.
      \return	true if the jobfile could be written, false otherwise
    */
    public function write2Spool() {

        // TODO: the spool directory has to be read from the config, once it
        // is defined there (see issue #411).
        $jobfilePath = realpath(dirname(__FILE__) . "/../run/spool/new");
        $jobfileName = tempnam($jobfilePath, "hrm_jobfile_");

        // tempnam() diverts to the system temp folder in case the directory
        // specified is not writable, so we have to catch this:
        if (!(substr($jobfileName, 0, strlen($jobfilePath)) === $jobfilePath)) {
            report("Could not access spooldir for creating a job file.", 0);
            report("Make sure '$jobfilePath' exists and is writable!", 0);
            return False;
        }

        // tempnam() creates files with mode 0600, so we have to adjust this:
        if (!chmod($jobfileName, 0664)) {
            return False;
        }
        
        $jobfileHandle = fopen($jobfileName, "w");
        if (!$jobfileHandle ) {
            report("Unable to open file '$jobfileName'!", 0);
            /*
             * Why are we waiting 15 seconds, and then returning? Nothing will
             * have changed then - so either we retry after that period, or we
             * can return immediately.
            report ("Waiting 15 seconds...", 1);
            sleep(15);
            */
            return False;
        }
        
        $result = (fwrite($jobfileHandle, $this->controller) > 0);
        fclose($jobfileHandle);
        report("Wrote job description file '$jobfileName'.", 1);

        return $result;
    }

}

?>
