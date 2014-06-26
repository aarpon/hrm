<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

require_once( "User.inc.php" );
require_once( "JobDescription.inc.php" );
require_once( "Fileserver.inc.php" );

class G3CPieController {

    /*!
     \brief $controller
     \var   String containing relevant information for the G3CPie job
    */
    public $controller;

    /*!
     \var    $jobDescription
     \brief  JobDescription object: unformatted microscopic & restoration data
    */
    private $jobDescription;

    /*!
     \brief $sectionsArray
     \var   Array with the main G3CPie fields.
    */
    private $sectionsArray;

    /*!
     \brief $hrmJobFileArray
     \var   Array with fields for the HRM section of the controller.
    */
    private $hrmJobFileArray;

    /*!
     \brief $hrmJobFileList 
     \var   HRM section of the controller sorted properly for G3CPie.
    */
    private $hrmJobFileList;

    /*!
     \brief $hucoreArray
     \var   Array with fields for the deconvolution section of the controller.
    */
    private $hucoreArray;

    /*!
     \brief $hucoreList
     \var   Section of the controller where to specify details about HuCore.
    */
    private $hucoreList;

    /*!
     \brief $inputFilesArray;
     \var   Array with fields for the input file section of the controller.
    */
    private $inputFilesArray;

    /*!
     \brief $inputFilesList 
     \var   Input file section of the controller sorted properly for G3CPie.
    */
    private $inputFilesList;
    
        /* ------------------------ Constructor ----------------------------- */   
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
        $this->assembleController();
    }


        /* ----------------------- Initialization ---------------------------- */
    /*!
     \brief  Sets general class properties to initial values.
    */
    private function initializeSections() {
        
        $this->sectionsArray = array ( 'hrmjobfile'  ,
                                       'hucore',   
                                       'inputfiles' );        
        
        $this->hrmJobFileArray = array( 'version'   =>  '2',
                                        'username'  =>  '',
                                        'jobtype'   =>  'hucore');
        
        $this->hucoreArray = array( 'executable'    =>   '',
                                    'template'      =>   '');

        $this->inputFilesArray = array( 'file'      =>   '');
    }

    /*!
     \brief  Sets the HRM job file section field.
    */
    private function setHrmJobFileSectionList() {
        $this->hrmJobFileList = "";
        
        foreach ($this->hrmJobFileArray as $key => $value) {
	    $this->hrmJobFileList .= $key;
            switch ( $key ) {
                case "version":
                    $this->hrmJobFileList .= " = " . $value;
                    break;
                case "username":
                    $this->hrmJobFileList .= " = ";
                    $this->hrmJobFileList .= $this->jobDescription->owner()->name();
                    break;
                case "jobtype":
                    $this->hrmJobFileList .= " = " .  $value;
                    break;
                default:
                    error_log("Unimplemented HRM job file section field: $key");
            }
            $this->hrmJobFileList .= "\n";
        }
    }

    /*!
     \brief  Sets the deconvolution section field.
    */
    private function setHuCoreSectionList() {
        global $local_huygens_core;

        $this->hucoreList = "";
        $templatePath = $this->jobDescription->sourceFolder();
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
                    $this->hucoreList .= $this->jobDescription->getHuTemplateName();
                    break;
                default:
                    error_log("Unimplemented HRM decon section field: $key");
            }
            $this->hucoreList .= "\n";
        }
    }

    /*!
     \brief  Sets the input file section field.
    */
    private function setInputFilesSectionList() {
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
            switch ($section) {
                case "hrmjobfile":
                    $this->controller .= "[" . $section . "]" . "\n";
                    $this->controller .= $this->hrmJobFileList;
                    break;
                case "hucore":
                    $this->controller .= "[" . $section . "]" . "\n";
                    $this->controller .= $this->hucoreList;
                    break;
                case "inputfiles":
                    $this->controller .= "[" . $section . "]" . "\n";
                    $this->controller .= $this->inputFilesList;
                    break;
                default:
                    error_log("Unimplemented controller section: $section");
            }
	    $this->controller .= "\n";
        }
    }
}

?>