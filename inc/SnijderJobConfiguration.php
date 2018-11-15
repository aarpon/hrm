<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

namespace hrm;

use hrm\user\User;
use hrm\job\JobDescription;
use hrm\Fileserver;

class SnijderJobConfiguration {

    /*!
     \brief $configuration
     \var   String containing information for the Snijder job.
    */
    public $configuration;

    /*!
     \var    $jobDescription
     \brief  JobDescription object.
    */
    private $jobDescription;

    /*!
     \brief $sectionsArray
     \var   Array with the main Snijder fields.
    */
    private $sectionsArray;

    /*!
     \brief $snijderJobFileArray
     \var   Array with the file fields.
    */
    private $snijderJobFileArray;

    /*!
     \brief $snijderJobFileList 
     \var   List with the files.
    */
    private $snijderJobFileList;

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
     \var   Input file section sorted properly for Snijder.
    */
    private $inputFilesList;

    /*!
     \brief  $taskPriorityArray
     \var    Array with fields for the task priorities.
    */
    private $taskPriorityArray;


    /* ------------------------------------------------------------------------ */
    
    /*!
     \brief  Constructor
     \param  $jobDescription JobDescription object
    */
    public function __construct( $jobDescription ) {
        $this->jobDescription  = $jobDescription;
        $this->initializeSections();
        $this->setSnijderJobFileSectionList();
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
            'snijderjob'  ,
            'hucore',
            'deletejobs',
            'inputfiles'
        );        
        
        $this->snijderJobFileArray = array (
            'version'       =>  '7',
            'username'      =>  '',
            'useremail'     =>  '',
            'jobtype'       =>  '',
            'timestamp'     =>  ''
        );
        
        $this->deleteJobsArray = array (
            'ids'           =>  ''
        );

        $this->hucoreArray = array (
            'tasktype'    =>   '',
            'executable'    =>   '',
            'template'      =>   ''
        );
        
        $this->inputFilesArray = array (
            'file'          =>   ''
        );
        
        /* Priorities stated in 'nice' units. */
        $this->tasksPriorityArray = array (
            'decon'        =>   '20',
            'snr'          =>   '15',
            'previewgen'   =>   '5',
            'deletejobs'   =>   '1'
        );
    }
    
    
    /* ----------------------------- Utils ------------------------------ */

    /*!
      \brief   Assigns a job type to a task type (decon, previewgen, etc).
      \return  The job type.
    */
    private function taskType2JobType() {
        $taskType = $this->jobDescription->getTaskType();
        
        switch ( $taskType ) {
        case "snr":
        case "previewgen":
        case "decon":
            $jobType = "hucore";
            break;
        case "deletejobs":
            $jobType = $taskType;
            break;
        default:
            error_log("Impossible to set job type for task type $jobType");
        }
        
        return $jobType;
    }

    /* ----------------------------------------------------------------- */
    
    /*!
     \brief  Sets the Snijder job file section field.
    */
    private function setSnijderJobFileSectionList() {
        $this->snijderJobFileList = "";
        
        $user = $this->jobDescription->owner();
        
        foreach ($this->snijderJobFileArray as $key => $value) {
            $this->snijderJobFileList .= $key;
            switch ( $key ) {
            case "version":
                $this->snijderJobFileList .= " = " . $value;
                break;
            case "username":
                $this->snijderJobFileList .= " = ";
                $this->snijderJobFileList .= $user->name();
                break;
            case "useremail":
                $this->snijderJobFileList .= " = ";
                $this->snijderJobFileList .= $user->emailAddress();
                break;            
            case "jobtype":
                $this->snijderJobFileList .= " = " . $this->taskType2JobType();
                break;            
            case "id":
                $this->snijderJobFileList .=  " = " . $this->jobDescription->getJobID();
                break;
            case "timestamp":
                $this->snijderJobFileList .= " = " . microtime(true);
                break;
            default:
                error_log("Unimplemented Snijder job file section field: $key");
            }
            $this->snijderJobFileList .= "\n";
        }
    }

    /*!
     \brief  Sets the ID  section field.
    */
    private function setDeleteJobsSectionList() {
        if ($this->jobDescription->getTaskType() != "deletejobs") {
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

        if ($this->jobDescription->getTaskType() == "deletejobs") {
            return;
        }

        $this->hucoreList = "";
        $templatePath = $this->jobDescription->sourceFolder();
        $templateName = $this->jobDescription->getHuTemplateName();
        foreach ($this->hucoreArray as $key => $value) {
            $this->hucoreList .= $key;
            switch ( $key ) {
                case "tasktype":
                    $this->hucoreList .= " = ";
                    $this->hucoreList .= $this->jobDescription->getTaskType();
                break;
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

        if ($this->jobDescription->getTaskType() == "deletejobs") {
            return;
        }
        
        $numberedFiles = "";

        $fileCnt = 0;
        $filePath   = $this->jobDescription->sourceFolder();
        $file = $this->jobDescription->file();
        $fileCnt++;
        $numberedFiles .= "file" . $fileCnt . " = ";
        $numberedFiles .= $filePath . $file . "\n";        

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

            if ($this->jobDescription->getTaskType()  == "deletejobs")  {
                if ($section  == "hucore" || $section == "inputfiles") {
                    continue;
                }
            }

            if ($this->jobDescription->getTaskType() !=  "deletejobs") {
                if ($section  == "deletejobs") {
                    continue;
                }
            }
            
            $this->controller .= "[" . $section . "]" . "\n";
            
            switch ($section) {
            case "snijderjob":
                $this->controller .= $this->snijderJobFileList;
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
    
    
    /**
     * Writes the Snijder configuration to the spool folder
     * @return True if the configuration could be written, false otherwise
     */
    public function write2Spool() {

        /* TODO: read this path from a configuration variable. */
        $controllerPath = "/opt/spool/snijder/spool/new";
        $controllerName = tempnam($controllerPath, "snijder_");
        if (!chmod($controllerName, 0664)) {  /*Due to  'tempnam'. */
            return False;
        }
        
        $controllerHandle = fopen($controllerName, "w");
        if (!$controllerHandle ) {
            Log::info("Impossible to open file $controllerName", 0);
            Log::info("Waiting 15 seconds...", 1);
            sleep(15);
            return False;
        }
        
        $result = (fwrite($controllerHandle, $this->controller) > 0);
        fclose($controllerHandle);
        Log::info("Wrote Snijder configuration file $controllerName", 2);        

        return $result;
    }

}

?>
