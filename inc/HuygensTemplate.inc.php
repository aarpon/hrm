<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

  /*!
   \class  HuygensTemplate
   \brief  Converts deconvolution parameters into a Huygens batch template.

   This class builds Tcl-compliant nested lists which summarize the tasks and 
   properties of the deconvolution job. Tasks describing the thumbnail products
   of an HRM deconvolution job are also included. The resulting structure is 
   a Huygens batch template formed by nested lists.

   Template structure:

   - 1 Job:
       - Job info.
       - Job tasks list:
           - Set environment
           - Set task ID
       - Set environment: 
           - resultDir
           - perJobThreadCnt
           - concurrentJobCnt
           - exportFormat
           - timeOut
       - Set image processing list:
           - Set image processing info:
               - state
               - tag
               - timeStartAbs
               - timeOut
           - Set image processing subtasks:
               - imgOpen
               - setp
               - deconvolution algorithm: one per channel
               - previewGen (one per thumbnail type)
               - imgSave
           - Set subtasks details (imgOpen, setp,..)           
  */

require_once( "User.inc.php" );
require_once( "JobDescription.inc.php" );
require_once( "Fileserver.inc.php" );

class HuygensTemplate {

    /*!
      \var    $jobInfoArray
      \brief  Array with components of job info.
    */
    private $jobInfoArray;

    /*!
     \var     $jobInfoList
     \brief   A Tcl list with job information for the template header.
     */
    private $jobInfoList;
    
    /*!
      \var    $jobTasksArray
      \brief  Array with the main job tasks.
    */
    private $jobTasksArray;

    /*!
     \var     $jobTasksList
     \brief   A Tcl list with the names of the main job tasks.
    */
    private $jobTasksList;
    
    /*!
      \var    $envArray
      \brief  Array with data for the setEnv task.
    */
    private $envArray;

    /*!
      \var    $envList
      \brief  A Tcl list with environment data: number of cores, timeout, etc. 
    */
    private $envList;

    /*!
     \var     $imgProcessArray
     \brief   Array with restoration and thumbnail-related operations.
    */
    private $imgProcessArray;

    /*!
     \var     $imgProcessInfoArray
     \brief   Array with data for the info field of the img process task.
    */
    private $imgProcessInfoArray;

    /*!
     \var     $imgProcessTasksArray
     \brief   Array with data for the tasklist field of the img process task.
    */
    private $imgProcessTasksArray;

    /*!
     \var     $imgProcessList
     \brief   A Tcl list with restoration and thumbnail operations.
    */
    private $imgProcessList;

    /*!
     \var     $expFormatArray
     \brief   Array with data for the environment export format field.
    */
    private $expFormatArray;

    /*!
     \var    $imgOpenArray
     \brief  Array with information for the image open subtask.
    */
    private $imgOpenArray;

    /*!
     \var    $imgSaveArray
     \brief  Array with information for the image save subtask.
    */
    private $imgSaveArray;

    /*!
     \var    $adjblArray;
     \brief  Array with information for the image adjbl subtask.
    */
    private $adjblArray;

    /*!
     \var    $algArray;
     \brief  Array with information for the image cmle/qmle subtask.
    */
    private $algArray;

    /*!
      \var    $setpArray
      \brief  Array with data for the setp subtask.
    */
    private $setpArray;

    /*!
      \var    $setpConfArray
      \brief  Array with parameter confidence levels for the setp subtask.
    */
    private $setpConfArray;


    /*!
      \var    $setpList
      \brief  A Tcl list with data for the 'set parameter' subtask.
    */
    private $setpList;

    /*!
     \var     $template
     \brief   Batch template containing the deconvolution job and thumbnail tasks.
    */
    public $template;

    /*!
      \var    $srcImage
      \brief  Path and name of the source image.
    */
    private $srcImage;

    /*!
      \var    $destImage
      \brief  Path and name of the deconvolved image.
    */
    private $destImage;

    /*!
      \var    $HuImageFormat
      \brief  The source image format, as coded in the HuCore confidence
      \brief  level table, whether leica, r3d, etc. Don't mistake for the file
      \brief  extension they are some times different, as in the leica case.
    */
    private $HuImageFormat;

    /*!
      \var    $jobDescription
      \brief  JobDescription object: unformatted microscopic & restoration data
    */
    private $jobDescription;

    /*!
      \var    $microSetting
      \brief  A ParametersSetting object: unformatted microscopic parameters.
    */
    private $microSetting;

    /*!
      \var    $deconSetting
      \brief  A TaskSetting object: unformatted restoration parameters.
    */
    private $deconSetting;

    /*!
     \var     $compareZviews
     \brief   A boolean to know whether an image is small enough as to
     \brief   be able to create its largest JPEGs.
    */
    public $compareZviews;

    /*!
     \var     $compareTviews
     \brief   A boolean to know whether an image is small enough as to
     \brief   be able to create its largest JPEGs.
    */
    public $compareTviews;

    /*! 
     \var     $thumbCnt
     \brief   An integer that keeps track of the number of preview tasks.
    */
    private $thumbCnt;

    /*!
     \var     $sizeX
     \brief   An integer to keep the X dimension of the image.
    */
    private $sizeX;

    /*!
     \var     $sizeY
     \brief   An integer to keep the Y dimension of the image.
    */
    private $sizeY;

    /*!
     \var     $sizeZ
     \brief   An integer to keep the Z dimension of the image.
    */
    private $sizeZ;

    /*!
     \var     $sizeT
     \brief   An integer to keep the T dimension of the image.
    */
    private $sizeT;

    /*!
     \var     $sizeC
     \brief   An integer to keep the channel dimension of the image.
    */
    private $sizeC;     

    /* ---------------------------- Constructor ------------------------------- */

    /*!
     \brief       Constructor
     \param       $jobDescription JobDescription object
    */
    public function __construct($jobDescription) {
        $this->initialize($jobDescription);
        $this->setJobInfoList();
        $this->setJobTasksList();
        $this->setEnvList();
        $this->setImgProcessList();
        $this->assembleTemplate();
    }

    /* ------------------------- Initialization ------------------------------ */

    /*!
     \brief       Sets general class properties to initial values
    */
    private function initialize($jobDescription) {
        $this->jobDescription = $jobDescription;
        $this->microSetting   = $jobDescription->parameterSetting;
        $this->deconSetting   = $jobDescription->taskSetting;

        $this->initializeImg();
        $this->initializeThumbCounter();
        $this->initializeJobInfo();
        $this->initializeJobTasks();
        $this->initializeEnvironment();
        $this->initializeImgProcessing();
    }

    private function initializeImg( ) {
        $this->setSrcImage();
        $this->setDestImage();
        $this->setHuImageFormat();
        $this->setImageDimensions();
    }

    /*!
     \brief       Resets the counter of thumbnail tasks
    */
    private function initializeThumbCounter( ) {
        $this->thumbCnt = 0;
    }

    private function initializeJobInfo() {

        $this->jobInfoArray = 
            array ('title'                      => 'Batch Processing template',
                   'version'                    => '2.2',
                   'templateName'               => '',
                   'date'                       => '',
                   'listID'                     => 'info');
    }

    private function initializeJobTasks() {
        
        $this->jobTasksArray = 
            array ( 'setEnv'                    => '',
                    'taskID:0'                  => '',
                    'listID'                    => 'taskList' );
    }

    /*!
     \brief       Sets env array with data: number of cores, timeout, etc.
    */
    private function initializeEnvironment( ) {
        $this->envArray = 
            array ( 'resultDir'                 => '',
                    'perJobThreadCnt'           => 'auto',
                    'concurrentJobCnt'          => '1',
                    'OMP_DYNAMIC'               => '1',
                    'timeOut'                   => '10000',
                    'exportFormat'              => '',
                    'listID'                    => 'setEnv' );

        $this->expFormatArray = 
            array ( 'type'                      =>  '',
                    'multidir'                  =>  '',
                    'cmode'                     =>  'scale' );
    }

    /*!
     \brief       Sets array with microscopic, restoration and preview operations
     \todo        Add a field 'zdrift' when it is implemented in the GUI.
    */
    private function initializeImgProcessing( ) {

        $this->imgProcessArray = 
            array ( 'info'                      => '',
                    'taskList'                  => '',
                    'listID'                    => 'taskID:0' );


        /* As long as the Huygens Scheduler receives only one job at a time
         the task will not be queued when it arrives at the Scheduler but
         executed immediatly, thus its state will be 'readyToRun', invariably. */

        /* All metadata will be accepted as long as the template counterparts
         don't exist. The accepted confidence level is therefore: "default". */
        $this->imgProcessInfoArray = 
            array ( 'state'                     => 'readyToRun',
                    'tag'                       => '',
                    'timeStartAbs'              => '',
                    'timeOut'                   => '10000',
                    'userDefConfidence'         => 'default',
                    'listID'                    => 'info' );

        $this->imgProcessTasksArray = 
            array ('open'                       =>  'imgOpen',
                   'setParameters'              =>  'setp',
                   'adjustBaseline'             =>  'adjbl',
                   'algorithms'                 =>  '',
                   'XYXZRawAtSrcDir'            =>  'previewGen',
                   'XYXZRawLifAtSrcDir'         =>  'previewGen',
                   'XYXZRawAtDstDir'            =>  'previewGen',
                   'XYXZDecAtDstDir'            =>  'previewGen',
                   'orthoRawAtDstDir'           =>  'previewGen',
                   'orthoDecAtDstDir'           =>  'previewGen',
                   'ZMovieDecAtDstDir'          =>  'previewGen',
                   'TimeMovieDecAtDstDir'       =>  'previewGen',
                   'TimeSFPMovieDecAtDstDir'    =>  'previewGen',
                   'SFPRawAtDstDir'             =>  'previewGen',
                   'SFPDecAtDstDir'             =>  'previewGen',
                   'ZComparisonAtDstDir'        =>  'previewGen',
                   'TComparisonAtDstDir'        =>  'previewGen',
                   'save'                       =>  'imgSave',
                   'listID'                     =>  'taskList');

        $this->imgOpenArray = 
            array ( 'path'                      =>  '',
                    'subImage'                  =>  '',
                    'series'                    =>  '',
                    'index'                     =>  '0',
                    'listID'                    =>  'imgOpen' );

        $this->setpArray  = 
            array ( 'completeChanCnt'           => '',
                    'micr'                      => '',
                    's'                         => '',
                    'iFacePrim'                 => '0.0',
                    'iFaceScnd'                 => '0.0',
                    'pr'                        => '',
                    'imagingDir'                => '',
                    'ps'                        => '',
                    'objQuality'                => 'good',
                    'pcnt'                      => '',
                    'ex'                        => '',
                    'em'                        => '',
                    'exBeamFill'                => '2.0',
                    'ri'                        => '',
                    'ril'                       => '',
                    'na'                        => '',
                    'listID'                    => 'setp' );

        $this->setpConfArray  =
            array ( 'completeChanCnt'           =>  '',
                    'micr'                      =>  'parState,micr',
                    's'                         =>  'parState,s',
                    'iFacePrim'                 =>  'parState,iFacePrim',
                    'iFaceScnd'                 =>  'parState,iFaceScnd',
                    'pr'                        =>  'parState,pr',
                    'imagingDir'                =>  'parState,imagingDir',
                    'ps'                        =>  'parState,ps',
                    'objQuality'                =>  'parState,objQuality',
                    'pcnt'                      =>  'parState,pcnt',
                    'ex'                        =>  'parState,ex',
                    'em'                        =>  'parState,em',
                    'exBeamFill'                =>  'parState,exBeamFill',
                    'ri'                        =>  'parState,ri',
                    'ril'                       =>  'parState,ril',
                    'na'                        =>  'parState,na',
                    'listID'                    =>  'setp' );

        $this->adjblArray = 
            array ( 'enabled'                   =>  '0',
                    'ni'                        =>  '0',
                    'listID'                    =>  'adjbl' );

        $this->algArray   = 
            array ( 'q'                         =>  '',
                    'brMode'                    =>  '',
                    'it'                        =>  '',
                    'bgMode'                    =>  '',
                    'bg'                        =>  '',
                    'sn'                        =>  '',
                    'blMode'                    =>  'auto',
                    'pad'                       =>  'auto',
                    'psfMode'                   =>  '',
                    'psfPath'                   =>  '',
                    'timeOut'                   =>  '36000',
                    'mode'                      =>  'fast',
                    'itMode'                    =>  'auto',
                    'listID'                    =>  '' );

        $this->imgSaveArray = 
            array ( 'rootName'                  =>  '',
                    'listID'                    =>  'imgSave' );
    }

    /* --------------------------- Task list builders -------------------------- */

    /*!
     \brief       Sets the info header of the batch template.
    */
    private function setJobInfoList( ) {
        
        $jobInfo = "";

        foreach ($this->jobInfoArray as $key => $value) {

            if ($key != "listID") {
                $jobInfo .= " " . $key . " ";
            }
            
            switch ( $key ) {
            case 'version':
                $jobInfo .= $value;
                break;
            case 'title':
                $jobInfo .= $this->string2tcllist($value);
                break;
            case 'templateName':
                $jobInfo .= $this->getTemplateName();
                break;
            case 'date':
                $jobInfo .= $this->getTemplateDate();
                break;
            case 'listID':
                $jobInfo = $this->string2tcllist($jobInfo);
                $this->jobInfoList = $value . " " . $jobInfo;
                break;
            default:
                error_log("Job info field $key not yet implemented.");       
            }
        }
    }

    /*!
     \brief       Sets the main job tasks: setEnv and taskIDs.
    */
    private function setJobTasksList() {

        $jobTasks = "";

        foreach ($this->jobTasksArray as $key => $value) {

            if ($key != "listID") {
                $jobTasks .= " " . $key . " ";
            }
            
            switch ( $key ) {
            case 'setEnv':
            case 'taskID:0':
                break;
            case 'listID':
                $jobTasks = $this->string2tcllist($jobTasks);
                $this->jobTasksList = $value . " " . $jobTasks;       
                break;
            default:
                error_log("Job task $key not yet implemented.");
            }
        }
    }

    /*!
     \brief       Sets a Tcl list with env data: number of cores, timeout, etc. 
    */
    private function setEnvList( ) {

        $env = "";

        foreach ($this->envArray as $key => $value) {

            if ($key != "listID") {
                $env .= " " . $key . " ";
            }

            switch ( $key ) {
            case 'resultDir':
                $env .= $this->string2tcllist($this->getDestDir());
                break;
            case 'exportFormat':
                $env .= $this->getExportFormat();
                break;
            case 'listID':
                $env = $this->string2tcllist($env);
                $this->envList = $value . " " . $env;
                break;
            case 'perJobThreadCnt':
            case 'concurrentJobCnt':
            case 'OMP_DYNAMIC':
            case 'timeOut':
                $env .= $value;
                break;
            default:
                error_log("Environment field $key not yet implemented");
            }
        }
    }
    
    /*!
     \brief       Sets a Tcl list with restoration and thumbnail operations
    */
    private function setImgProcessList( ) {
        
        $imgProcess = "";
        
        foreach ($this->imgProcessArray as $key => $value) {
            
            if ($key != "listID") {
                $imgProcess .= " ";
            }
          
            switch ( $key ) {
            case 'info':
                $imgProcess .= $this->getImgProcessInfo();
                break;
            case 'taskList':
                $imgProcess .= $this->getImgProcessTasks();
                $imgProcess .= $this->getImgProcessSubTasks();
                break;
            case 'listID':
                $imgProcess = $this->string2tcllist($imgProcess);
                $this->imgProcessList = $value . " " .$imgProcess;
                break;
            default:
                error_log("Image processing task $key not yet implemented");
            }
        }
    }

    /*!
     \brief       Puts the Huygens Batch template together
    */
    private function assembleTemplate( ) {
        $this->template =  $this->jobInfoList . "\n";
        $this->template .= $this->jobTasksList . "\n";
        $this->template .= $this->envList . "\n ";
        $this->template .= $this->imgProcessList;
    }

    /*!
     \brief       Get the env export format feature as a list of options
     \return      Tcl-complaint nested list with the export format options
    */
    private function getExportFormat( ) {

        $exportFormat = "";
        foreach ($this->expFormatArray as $key => $value) {
            $exportFormat .= " " . $key . " ";

            switch( $key ) {
            case 'type':
                $exportFormat .= $this->getOutputFileType();
                break;
            case 'multidir':
                $exportFormat .= $this->getMultiDirOpt();
                break;
            case 'cmode':
                $exportFormat .= $value;
                break;
            default:
                error_log("Export format option $key not yet implemented");
            }
        }

        return $this->string2tcllist($exportFormat);
    }

    /*!
     \brief       Gets scheduling information oo the tasks.
     \return      The Tcl-compliant nested list with task information
    */
    private function getImgProcessInfo( ) {

        $taskInfo = "";
        foreach ($this->imgProcessInfoArray as $key => $value) {

            if ($key != "listID") {
                $taskInfo .= " " . $key . " ";
            }

            switch( $key ) {
            case 'state':
                $taskInfo .= $value;
                break;
            case 'tag':
                $taskInfo .= $this->getTaskTag();
                break;
            case 'timeStartAbs':
                $taskInfo .= time();
                break;
            case 'timeOut':
                $taskInfo .= $value;
                break;
            case 'userDefConfidence':
                $taskInfo .= $value;
                break;
            case 'listID':
                $taskInfo = $this->string2tcllist($taskInfo);
                $taskInfo = $value . " " . $taskInfo;
                break;
            default:
                error_log("Info option $key not yet implemented");
            }
        }
        
        return $taskInfo;
    }

    /*!
     \brief       Gets the Huygens subtask names of a deconvolution. 
     \return      The Tcl-compliant nested list with subtask names
    */
    private function getImgProcessTasks( ) {

        $taskList = "";

        foreach ($this->imgProcessTasksArray as $key => $value) {
            switch ( $key ) {
            case 'open':
            case 'save':
            case 'setParameters':
            case 'adjustBaseline':
            case 'algorithms':
            case 'XYXZRawAtSrcDir':
            case 'XYXZRawLifAtSrcDir':
            case 'XYXZRawAtDstDir':
            case 'XYXZDecAtDstDir':
            case 'orthoRawAtDstDir':
            case 'orthoDecAtDstDir':
            case 'SFPRawAtDstDir':
            case 'SFPDecAtDstDir':
            case 'ZMovieDecAtDstDir':
            case 'TimeSFPMovieDecAtDstDir':
            case 'TimeMovieDecAtDstDir':
            case 'ZComparisonAtDstDir':
            case 'TComparisonAtDstDir':
                $task = $this->parseTask($key,$value);
                if ($task != "") {
                    $taskList .= $task ." ";
                }
                break;
            case 'listID':
                $taskList = $this->string2tcllist($taskList);
                $taskList = $value . " " . $taskList;
                break;
            default:
                error_log("Image process task $key not yet implemented");
            }
        }
        
        return $taskList;
    }

    /*!
     \brief       Gets specific details of each deconvolution task. 
     \return      The Tcl-compliant nested list with task details
    */
    private function getImgProcessSubTasks( ) {

        $this->initializeThumbCounter();

        $taskList = "";
        foreach ($this->imgProcessTasksArray as $key => $value) { 
            $taskList .= " ";
            switch ( $key ) {
            case 'open':
                $taskList .= $this->getImgProcessOpen($value);
                break;
            case 'save':
                $taskList .= $this->getImgProcessSave($value);
                break;
            case 'setParameters':
                $taskList .= $this->getImgProcessSetp($value);
                break;
            case 'adjustBaseline':
                $taskList .= $this->getImgProcessAdjbl($value);
                break;
            case 'algorithms':
                $taskList .= $this->getImgProcessAlgorithms();
                break;
            case 'XYXZRawAtSrcDir':
                $taskList .= $this->getImgProcessXYXZ($key,$value,"raw","src");
                break;
            case 'XYXZRawLifAtSrcDir':
                $taskList .= $this->getImgProcessXYXZ($key,$value,"raw","src",
                                                      "lif");
                break;
            case 'XYXZRawAtDstDir':
                $taskList .= $this->getImgProcessXYXZ($key,$value,"raw","dest");
                break;
            case 'XYXZDecAtDstDir':
                $taskList .= $this->getImgProcessXYXZ($key,$value,"dec","dest");
                break;
            case 'orthoRawAtDstDir':
                $taskList .= $this->getImgProcessOrtho($key,$value,"raw","dest");
                break;
            case 'orthoDecAtDstDir':
                $taskList .= $this->getImgProcessOrtho($key,$value,"dec","dest");
                break;
            case 'SFPRawAtDstDir':
                $taskList .= $this->getImgProcessSFP($key,$value,"raw","dest");
                break;
            case 'SFPDecAtDstDir':
                $taskList .= $this->getImgProcessSFP($key,$value,"dec","dest");
                break;
            case 'ZMovieDecAtDstDir':
                $taskList .= $this->getImgProcessMovie($key,$value,"dec","dest",
                                                       "ZMovie");
                break;
            case 'TimeSFPMovieDecAtDstDir':
                $taskList .= $this->getImgProcessMovie($key,$value,"dec","dest",
                                              "timeSFPMovie");
                break;
            case 'TimeMovieDecAtDstDir':
                $taskList .= $this->getImgProcessMovie($key,$value,"dec","dest",
                                                       "timeMovie");
                break;
            case 'ZComparisonAtDstDir':
                $taskList .= $this->getImgProcessZComparison($key,$value,"dest");
                break;
            case 'TComparisonAtDstDir':
                $taskList .= $this->getImgProcessTComparison($key,$value,"dest");
                break;
            case 'listID':
                break;
            default:
                $taskList = "";
                error_log("Image processing task $key not yet implemented.");
            }
        }

        return $taskList;
    }
    
    /*!
     \brief       Gets options for the 'image open' task
     \param       $task A task from the taskID array that should be 'imgOpen'.
     \return      Tcl list with the'Image open' task and its options
    */
    private function getImgProcessOpen($task) {

        $imgOpen = "";
        foreach ($this->imgOpenArray as $key => $value) {

           if ($key != "subImage" && $key != 'listID') {
                $imgOpen .= " " . $key . " ";
            }

            switch( $key ) {
            case 'path':
                $imgOpen .= $this->string2tcllist($this->srcImage);
                break;
            case 'series':
                $imgOpen .= $this->getSeriesMode();
                break;
            case 'index':
                $imgOpen .= " " . $value . " ";
                break;
            case 'subImage':
                if (isset($this->subImage)) {
                    $imgOpen .= " " . $key . " ";
                    $imgOpen .= $this->string2tcllist($this->subImage);
                }
                break;
            case 'listID':
                $imgOpen = $this->string2tcllist($imgOpen);
                $imgOpen = $value  . " " . $imgOpen;
                break;
            default:
                error_log("Image open option $key not yet implemented.");
            }
        }

        return $imgOpen;
    }

    /*!
     \brief       Gets options for the 'set parameters' task
     \param       $task A task from the taskID array that should be 'setp'.
     \return      Tcl list with the 'Set parameters' task and its options
    */
    private function getImgProcessSetp( ) {

        $setp = "";
        foreach ($this->setpArray as $key => $value) { 

            if ($key != "listID") {
                $setp .= " " . $key . " ";
            }

            switch ( $key ) {
            case 'completeChanCnt':
                $setp .= $this->getNumberOfChannels();
                break;
            case 'micr':
                $setp .= $this->getMicroscopeType();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getMicroTypeConfidenceList();
                break;
            case 's':
                $setp .= $this->getSamplingSizes();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getSamplingConfidenceList();
                break;
            case 'iFacePrim':
                $setp .= $value;
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getiFacePrimConfidence();
                break;                
            case 'iFaceScnd':
                $setp .= $value;
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getiFaceScndConfidence();
                break;
            case 'pr':
                $setp .= $this->getPinholeRadius();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getPinholeRadiusConfidenceList();
                break;
            case 'imagingDir':
                $setp .= $this->getImagingDirection();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getImagingDirConfidenceList();
                break;
            case 'ps':
                $setp .= $this->getPinholeSpacing();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getPinSpacingConfidenceList();
                break;
            case 'objQuality':
                $setp .= $this->getObjQuality();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getObjQualityConfidenceList();
                break;                
            case 'pcnt':
                $setp .= $this->getExcitationPcnt();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getExcitationPcntConfidenceList();
                break;
            case 'ex':
                $setp .= $this->getExcitationLambda();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getExcitationLConfidenceList();
                break;
            case 'em':
                $setp .= $this->getEmissionLambda();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getEmissionLConfidenceList();
                break;
            case 'exBeamFill':
                $setp .= $this->getExBeamFill();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getExBeamConfidenceList();
                break;
            case 'ri':
                $setp .= $this->getMediumRefractiveIndex();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getMediumRIndexConfidenceList();
                break;
            case 'ril':
                $setp .= $this->getLensRefractiveIndex();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getLensRIndexConfidenceList();
                break;
            case 'na':
                $setp .= $this->getNumericalAperture();
                $setp .= " " . $this->setpConfArray[$key] . " ";
                $setp .= $this->getNumericalApertureConfidenceList();
                break;
            case 'listID':
                $setp = $this->string2tcllist($setp);
                $this->setpList = $value . " " . $setp;
                break;
            default:
                error_log("Setp field $key not yet implemented.");       
            }
        }

        return $this->setpList;
    }

    /*!
     \brief       Gets options for the 'image save' task
     \param       $task A task from the taskID array that should be 'imgSave'.
     \return      Tcl list with the 'Image save' task and its options
    */
    private function getImgProcessSave($task) {

        $imgSave = "";
        foreach ($this->imgSaveArray as $key => $value) {

            if ($key != "listID") {
                $imgSave .= " " . $key . " ";
            }

            switch( $key ) {
            case 'rootName':
                $outName  = $this->getDestImageBaseName();
                $imgSave .= $this->string2tcllist($outName);
                break;
            case 'listID':
                $imgSave = $this->string2tcllist($imgSave);
                $imgSave = " " . $value . " " . $imgSave;
                break;
            default:
                error_log("Image save option $key not yet implemented.");
            }
        }

        return $imgSave;
    }

    /*!
     \brief       Gets options for the 'adjust baseline' task
     \param       $task A task from the taskID array that should be 'adjbl'.
     \return      Tcl list with the 'Adjust baseline' task and its options
    */
    private function getImgProcessAdjbl($task) {

        $imgAdjbl = "";
        foreach ($this->adjblArray as $key => $value) {

            if ($key != "listID") {
                $imgAdjbl .= " " . $key . " ";
            }

            switch( $key ) {
            case 'enabled':
                $imgAdjbl .= $value;
                break;
            case 'ni':
                $imgAdjbl .= $value;
                break;
            case 'listID':
                $imgAdjbl = $this->string2tcllist($imgAdjbl);
                $imgAdjbl = $value . " " . $imgAdjbl;
                break;
            default:
                error_log("Image adjbl option $key not yet implemented.");
            }
        }

        return $imgAdjbl;
    }

    /*!
     \brief       Gets options for the 'algorithm' task. One channel.
     \param       $channel A channel
     \return      Tcl list with the deconvolution 'algorithm' task and its options
    */
    private function getTaskAlgorithm($channel) {

        $imgAlg = "";
        foreach ($this->algArray as $key => $value) {

            if ($key != "mode" && $key != "itMode" && $key != 'listID') {
                $imgAlg .= " " . $key . " ";
            }

            switch ( $key ) {
            case 'q':
                $imgAlg .= $this->getQualityFactor();
                break;
            case 'brMode':
                $imgAlg .= $this->getBrMode();
                break;
            case 'it':
                $imgAlg .= $this->getIterations();
                break;
            case 'bgMode':
                $imgAlg .= $this->getBgMode();
                break;
            case 'bg':
                $imgAlg .= $this->getBgValue($channel);
                break;
            case 'sn':
                $imgAlg .= $this->getSnrValue($channel);
                break;
            case 'psfMode':
                $imgAlg .= $this->getPsfMode();
                break;
            case 'psfPath':
                $imgAlg .= $this->getPsfPath($channel);
                break;
            case 'blMode':
                $imgAlg .= $value;
                break;
            case 'pad':
                $imgAlg .= $value;
                break;
            case 'timeOut':
                $imgAlg .= $value;
                break;
            case 'mode':
                if ($this->getAlgorithm() == "cmle") {
                    $imgAlg .= " " . $key . " ";
                    $imgAlg .= $value;
                }
                break;
            case 'itMode':
                if ($this->getAlgorithm() == "qmle") {
                    $imgAlg .= " " . $key . " ";
                    $imgAlg .= $value;
                }
                break;
            case 'listID':
                break;
            default:
                error_log("Deconvolution option $key not yet implemented");
            }
        }

        return $this->string2tcllist($imgAlg);
    }

    /* -------------------------- Setp task ----------------------------------- */

    /*!
     \brief       Confidence level of iFacePrim.
     \return      The parameter confidence
     \todo        To be implemented in the GUI
    */
    private function getiFacePrimConfidence( ) {
        return $this->getParameterConfidence("iFacePrim",0);
    }

    /*!
     \brief       Confidence level of iFaceScnd.
     \return      The parameter confidence
     \todo        To be implemented in the GUI
    */
    private function getiFaceScndConfidence( ) {
        return $this->getParameterConfidence("iFaceScnd",0);
    }

    /*!
     \brief       Gets the pinhole radii. All channels.
     \return      Tcl list with the Pinhole radii.
    */
    private function getPinholeRadius( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $prList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $pinRadius = $this->getParameterValue("PinholeSize",$chanCnt);
            $prList .= " " . $pinRadius;
        }
        return  $this->string2tcllist($prList);
    }

    /*!
     \brief       Gets the pinhole radius. One channel.
     \param       $channel A channel
     \return      The pinhole radius.
    */
    private function getPinRadiusForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize")->value();       
        $pinholeRadius = $pinholeSize[$channel];
        return $pinholeRadius;
    }

    /*!
     \brief       Confidence levels of the pinhole radii. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getPinholeRadiusConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("PinholeSize",$chanCnt);
            $cList .= "  " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the microscope type of each channel. All channels.
     \return      The microscope type list
    */
    private function getMicroscopeType( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $microList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $microType = $this->getParameterValue("MicroscopeType",$chanCnt);
            $microList .= " " . $microType;
        }
        return $this->string2tcllist($microList);
    }            
           

    /*!
     \brief       Gets the microscope type of all channels.
     \param       $channel A channel
     \return      Tcl-list with the microscope types of all channels.
     \todo        To be implemented in the GUI
    */
    private function getMicroTypeForChannel($channel) {
        $microSetting = $this->microSetting;
        $microType = $microSetting->parameter('MicroscopeType')->translatedValue();
        return $microType;
    }

    /*!
     \brief       Confidence levels of the microscope type. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getMicroTypeConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("MicroscopeType",$chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the refractive indexes of all channels.
     \return      Tcl-list with the refractive indexes.
    */
    private function getLensRefractiveIndex( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $lensRIList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $lensRI = $this->getParameterValue("ObjectiveType",$chanCnt);
            $lensRIList .= " " . $lensRI;
        }
        return $this->string2tcllist($lensRIList);
    }

    /*!
     \brief       Gets the refractive index of one channel.
     \param       $channel A channel
     \return      The refractive index.
    */
    private function getLensRIForChannel($channel) {
        $microSetting = $this->microSetting;
        $lensRI = $microSetting->parameter('ObjectiveType')->translatedValue();
        return $lensRI;
    }

    /*!
     \brief       Confidence levels of the lens refractive index. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getLensRIndexConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("ObjectiveType",$chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the numerical apertures. All channels.
     \return      Tcl-list with the numerical apertures.
    */
    private function getNumericalAperture( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $numAperList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $numAper = $this->getParameterValue("NumericalAperture",$chanCnt);
            $numAperList .= " " . $numAper;
        }
        return $this->string2tcllist($numAperList);
    }

    /*!
     \brief       Gets the numerical aperture. One channel.
     \param       $channel A channel
     \return      The numerical aperture.
    */
    private function getNumApertureForChannel($channel) {
        $microSetting = $this->microSetting;
        $numAper = $microSetting->parameter('NumericalAperture')->value();

        return $numAper;
    }

    /*!
     \brief       Confidence levels of the numerical aperture index. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getNumericalApertureConfidenceList( ) {

        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("NumericalAperture",$chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the pinhole spacing. All channels.
     \return      Tcl-list with pinhole spacing.
    */
    private function getPinholeSpacing( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $pinsList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $pinSpacing = $this->getParameterValue("PinholeSpacing",$chanCnt);
            $pinsList .= " " . $pinSpacing;
        }
        return $this->string2tcllist($pinsList);
    }

    /*!
     \brief       Gets the pinhole spacing. One channel.
     \param       $channel A channel
     \return      The pinhole spacing.
    */
    private function getPinSpacingForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinSpacing = $microSetting->parameter("PinholeSpacing")->value();

        if ($this->getParameterValue("MicroscopeType",$channel) != "nipkow") {
            $pinSpacing = "";
        }
        return $pinSpacing;
    }

    /*!
     \brief       Confidence levels of the pinhole spacing. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getPinSpacingConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("PinholeSpacing",$chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the objective qualities. All channels.
     \return      Tcl-list with the objective qualities.
    */
    private function getObjQuality( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $objQList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $objQuality = $this->getParameterValue("ObjQuality",$chanCnt);
            $objQList .= " " . $objQuality;
        }
        return $this->string2tcllist($objQList);
    }

    /*!
     \brief       Gets the Objective Quality. One channel.
     \param       $channel A channel
     \return      The objective quality.
     \todo        To be implemented in the GUI
    */
    private function getObjQualityForChannel($channel) {
        $objQuality = "good";
        return $objQuality;
    }

    /*!
     \brief       Confidence levels of the object quality. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getObjQualityConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("ObjQuality",$chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the excitation photon counts. All channels.
     \return      Tcl-list with the excitation photon  counts.
    */
    private function getExcitationPcnt( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $excPcntList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $excPcnt = $this->getExcitationPcntForChannel($chanCnt);
            $excPcntList .= " " . $excPcnt;
        }
        return $this->string2tcllist($excPcntList);
    }

    /*!
     \brief       Gets the excitation photon count. One channel.
     \param       $channel A channel
     \return      Tcl-list with the excitation photon count.
    */
    private function getExcitationPcntForChannel($channel) {
        if ($this->microSetting->isTwoPhoton()) {
            $pcnt = 2;
        } else {
            $pcnt = 1;
        }
        return $pcnt;
    }

    /*!
     \brief       Confidence levels of the excitacion photon cnt. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getExcitationPcntConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("ExcitationPhotonCnt",0);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the excitation wavelengths. All channels.
     \return      Tcl-list with the excitation wavelengths.
    */
    private function getExcitationLambda( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $exLambdaList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $exLambda = $this->getParameterValue("ExcitationWavelength",$chanCnt);
            $exLambdaList .= " " . $exLambda;
        }
        return $this->string2tcllist($exLambdaList);
    }

    /*!
     \brief       Gets the excitation wavelength. One channel.
     \param       $channel A channel
     \return      Tcl-list with the excitation wavelength.
    */
    private function getExLambdaForChannel($channel) {
        $microSetting = $this->microSetting;
        $excitationLambdas = $microSetting->parameter("ExcitationWavelength");
        $excitationLambda = $excitationLambdas->value();
        return $excitationLambda[$channel];
    }

    /*!
     \brief       Confidence levels of the excitacion wavelength. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getExcitationLConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("ExcitationWavelength",
                                                    $chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }
    
    /*!
     \brief       Gets the excitation beam overfill factor. All channels.
     \return      Tcl-list with the excitation beam overfill factors.
    */
    private function getExBeamFill( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $exBeamList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $exBeam = $this->getParameterValue("ExBeamFactor",$chanCnt);
            $exBeamList .= " " . $exBeam;
        }
        return $this->string2tcllist($exBeamList);
    }

    /*!
     \brief       Gets the excitation beam overfill factor. One channel.
     \param       $channel A channel
     \return      Tcl-list with the excitation beam factor.
    */
    private function getExBeamForChannel($channel) {
        return 2.0;
    }

    /*!
     \brief       Confidence levels of the excitacion beam factor. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getExBeamConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("ExBeamFactor",0);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the emission wavelengths. All channels.
     \return      Tcl-list with the emission wavelengths.
    */
    private function getEmissionLambda( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $emLambdaList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $emLambda = $this->getParameterValue("EmissionWavelength",$chanCnt);
            $emLambdaList .= " " . $emLambda;
        }
        return $this->string2tcllist($emLambdaList);
    }

    /*!
     \brief       Gets the emission wavelength. One channel.
     \param       $channel A channel
     \return      The emission wavelength.
    */
    private function getEmLambdaForChannel($channel) {
        $microSetting = $this->microSetting;
        $emissionLambdas = $microSetting->parameter("EmissionWavelength");
        $emissionLambda = $emissionLambdas->value();
        return $emissionLambda[$channel];
    }
    
    /*!
     \brief       Confidence levels of the emission wavelength. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getEmissionLConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("EmissionWavelength",
                                                    $chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the imaging direction. All channels.
     \return      Tcl list with the 'imaging direction'.
    */
    private function getImagingDirection( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $dirList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $imagingDir = $this->getParameterValue("CoverslipRelativePosition",
                                                   $chanCnt);
            $dirList .= " " . $imagingDir;
        }
        return $this->string2tcllist($dirList);
    }

    /*!
     \brief       Gets the imaging direction. One channel.
     \param       $channel A channel
     \return      Whether the imaging is 'downward' or 'upward'
    */
    private function getImagingDirForChannel($channel) {
        $microSetting = $this->microSetting;
        $coverslip = $microSetting->parameter('CoverslipRelativePosition');
        $coverslipPos = $coverslip->value();
        if ($coverslipPos == 'farthest' ) {
            $imagingDir = "downward";
        } else {
            $imagingDir = "upward";
        }
        return $imagingDir;
    }

    /*!
     \brief       Confidence levels of the imaging direction. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getImagingDirConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("CoverslipRelativePosition",
                                                    $chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    /*!
     \brief       Gets the medium refractive index. All channels.
     \return      Tcl list with the 'medium refractive indexes'.
    */
    private function getMediumRefractiveIndex() {
        $numberOfChannels = $this->getNumberOfChannels();
        $MRIndexList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $MRIndex = $this->getParameterValue("SampleMedium",$chanCnt);
            $MRIndexList .= " " . $MRIndex;
        }
        return $this->string2tcllist($MRIndexList);
    }

    /*!
     \brief       Gets the medium refractive index. One channel.
     \param       $channel A channel
     \return      The medium refractive index
    */
    private function getMRIndexForChannel($channel) {
        $microSetting = $this->microSetting;
        $sampleMedium = $microSetting->parameter("SampleMedium");
        $refractiveIndx = $sampleMedium->translatedValue();
        return $refractiveIndx;
    }

    /*!
     \brief       Confidence levels of the medium refractive index. All channels.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getMediumRIndexConfidenceList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $cList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $cLevel = $this->getParameterConfidence("SampleMedium",$chanCnt);
            $cList .= " " . $cLevel;
        }
        return $this->string2tcllist($cList);
    }

    public function getSamplingSizeX( ) {
        if ($this->microSetting->sampleSizeX() != 0) {
            return $this->microSetting->sampleSizeX();
        } else {
            return "*";
        }
    }

    public function getSamplingSizeY( ) {
        if ($this->microSetting->sampleSizeY() != 0) {
            return $this->microSetting->sampleSizeY();
        } else {
            return "*";
        }
    }

    public function getSamplingSizeZ( ) {
        if ($this->microSetting->sampleSizeZ() != 0) {
            return $this->microSetting->sampleSizeZ();
        } else {
            return "*";
        }
    }

    public function getSamplingSizeT( ) {
        if ($this->microSetting->sampleSizeT() != 0) {
            return $this->microSetting->sampleSizeT();
        } else {
            return "*";
        }
    }

    /*!
     \brief       Gets the sampling sizes. All channels.
     \return      Tcl-list with the sampling sizes.
    */
    private function getSamplingSizes( ) {
        $sampling = $this->getSamplingSizeX();
        $sampling .= " " . $this->getSamplingSizeY();
        $sampling .= " " . $this->getSamplingSizeZ();
        $sampling .= " " . $this->getSamplingSizeT();

        return $this->string2tcllist($sampling);
    }

    /*!
     \brief       Confidence levels of the sampling sizes.
     \return      Tcl-list: whether verified, reported, estimated or default.
    */
    private function getSamplingConfidenceList( ) {
        $cList = $this->getParameterConfidence("CCDCaptorSizeX",0);
        $cList .= " " . $this->getParameterConfidence("CCDCaptorSizeX",0);
        $cList .= " " . $this->getParameterConfidence("ZStepSize",0);
        $cList .= " " . $this->getParameterConfidence("TimeInterval",0);
        return $this->string2tcllist($cList);
    }

    /* -------------------------- Algorithm task ------------------------------ */
    
    /*!
     \brief       Gets the brick mode.
     \return      Brick mode.
    */
    private function getBrMode( ) {
        $SAcorr = $this->getSAcorr();

        if ( $SAcorr[ 'AberrationCorrectionNecessary' ] == 1 ) {
            if ( $SAcorr[ 'PerformAberrationCorrection' ] == 0 ) {
                $brMode = 'one';
            } else {
                if ( $SAcorr[ 'AberrationCorrectionMode' ] == 'automatic' ) {
                    $brMode = 'auto';
                } else {
                    if ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'user' ) {
                        $brMode = 'one';
                    } elseif ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'slice' ) {
                        $brMode = 'sliceBySlice';
                    } elseif ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'few' ) {
                        $brMode = 'few';
                    } else {
                        error_log("Undefined brMode.");
                        $brMode = "";
                    }
                }
            }
        } else {
            $brMode = "one";
        }
        return $brMode;
    }

    /*!
     \brief       Gets the background mode.
     \return      Background mode.
    */
    private function getBgMode( ) {
        $bgParam = $this->deconSetting->parameter("BackgroundOffsetPercent");
        $bgValue = $bgParam->value();
        $internalValue = $bgParam->internalValue();
        if ($bgValue[0] == "auto" || $internalValue[0] == "auto") {
            $bgMode = "auto";
        } else if ($bgValue[0] == "object" || $internalValue[0] == "object") {
            $bgMode = "object";
        } else {
            $bgMode = "manual";
        }
        return $bgMode;
    }

    /*!
     \brief       Gets the background value. One channel.
     \param       $channel A channel
     \return      The background value.
    */
    private function getBgValue($channel) {
        $bgMode = $this->getBgMode();
        if ($bgMode == "auto") {
            $bgValue = 0.0;
        } elseif ($bgMode == "object") {
            $bgValue = 0.0;
        } elseif ($bgMode == "manual") {
            $deconSetting = $this->deconSetting;
            $bgRate = $deconSetting->parameter("BackgroundOffsetPercent")->value();
            $bgValue = $bgRate[$channel];
        } else {
            error_log("Unknown background mode for channel $channel.");
        }
        return $bgValue;
    }

    /*!
     \brief       Gets the SNR value. One channel.
     \param       $channel A channel
     \return      The SNR value.
    */
    private function getSnrValue($channel) {
        $deconSetting = $this->deconSetting;
        $snrRate = $deconSetting->parameter("SignalNoiseRatio")->value();       
        $snrValue = $snrRate[$channel];
         
        if ($this->getAlgorithm() == "qmle") {
            $indexValues = array  (1, 2, 3, 4, 5);
            $snrArray = array  ("low", "fair", "good", "inf", "auto");
            $snrValue = str_replace($indexValues, $snrArray, $snrValue);
        }

        return $snrValue;
    }

    /*!
     \brief       Gets the PSF mode.
     \return      PSF mode
    */
    private function getPsfMode( ) {
        $microSetting = $this->microSetting;
        $psfMode = $microSetting->parameter("PointSpreadFunction")->value();
        if ($psfMode == "theoretical") {
            $psfMode = "auto";
        } else {
            $psfMode = "file";
        }
        return $psfMode;
    }

    /*!
     \brief       Gets the PSF path.
     \param       $channel A channel
     \return      Psf path
    */
    private function getPsfPath($channel) {
        $psfPath = "";
        if ($this->getPsfMode() == "file") {
            $microSetting = $this->microSetting;
            $psfFiles = $microSetting->parameter("PSF")->value();
            $owner = $this->jobDescription->owner( );
            $fileserver = new Fileserver( $owner->name() );
            $path = $fileserver->sourceFolder();
            $psf = $path ."/". $psfFiles[$channel];
            $psfPath .= " " . $psf;
            $psfPath = trim($psfPath);
        }
        $psfPath = $this->string2tcllist($psfPath);

        return $psfPath;
    }

    /*!
     \brief       Gets the deconvolution quality factor.
     \return      The quality factor
    */
    private function getQualityFactor( ) {
        $deconSetting = $this->deconSetting;
        return $deconSetting->parameter('QualityChangeStoppingCriterion')->value();
    }

    /*!
     \brief       Gets the maximum number of iterations for the deconvolution.
     \return      The maximum number of iterations.
    */
    private function getIterations( ) {
        return $this->deconSetting->parameter('NumberOfIterations')->value();
    }

    /*!
     \brief       Gets the deconvolution algorithm.
     \return      Deconvolution algorithm.
    */
    private function getAlgorithm( ) {
        return $this->deconSetting->parameter('DeconvolutionAlgorithm')->value();
    }

    /*!
     \brief       Gets the spherical aberration correction.
     \return      Sperical aberration correction.
    */
    private function getSAcorr( ) {
        return $this->microSetting->getAberractionCorrectionParameters();
    }

    /* ------------------------- Thumbnail tasks------------------------------- */

    /*!
     \brief       Gets task information for XY and XZ previews
     \param       $key     A task key from the taskID array
     \param       $task    A task from the taskID array
     \param       $srcImg  Whether the raw image or the deconvolved
     \param       $destDir Whether to be saved in the source or the destination
     \return      The preview generation list
    */
    private function getImgProcessXYXZ($key,$task,$srcImg,$destDir,$lif = null) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }
        $previewGen = "image ";
        $previewGen .= $this->getImageType($srcImg);
        $previewGen .= " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= $this->getThumbnailDestFile($srcImg,"XYXZ",null,$lif);
        $previewGen .= " type ";
        $previewGen .= $this->getPreviewType("XYXZ");
        $previewGen = $this->string2tcllist($previewGen);
        $previewGen = " " . $task . " " . $previewGen;
        return $previewGen;
    }
    
    /*!
     \brief       Gets task information for ortho previews
     \param       $key     A task key from the taskID array
     \param       $task    A task from the taskID array
     \param       $srcImg  Whether the raw image or the deconvolved
     \param       $destDir Whether to be saved in the source or the destination
     \return      The preview generation list
    */
    private function getImgProcessOrtho($key,$task,$srcImg,$destDir) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }

        $previewGen = "image ";
        $previewGen .= $this->getImageType($srcImg);
        $previewGen .= " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= $this->getThumbnailDestFile($srcImg,"Ortho");
        $previewGen .= " type ";
        $previewGen .= $this->getPreviewType("orthoSlice");
        $previewGen .= " size ";
        $previewGen .= $this->getPreviewSize();
        $previewGen = $this->string2tcllist($previewGen);
        $previewGen = " " . $task . " " . $previewGen;
        return $previewGen;
    }
    
    /*!
     \brief       Gets task information for SFP previews
     \param       $key     A task key from the taskID array
     \param       $task    A task from the taskID array
     \param       $srcImg  Whether the raw image or the deconvolved
     \param       $destDir Whether to be saved in the source or the destination
     \return      The preview generation list
    */
    private function getImgProcessSFP($key,$task,$srcImg,$destDir) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }

        $previewGen = "image ";
        $previewGen .= $this->getImageType($srcImg);
        $previewGen .= " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= $this->getThumbnailDestFile($srcImg,"SFP");
        $previewGen .= " type ";
        $previewGen .= $this->getPreviewType("SFP");
        $previewGen = $this->string2tcllist($previewGen);
        $previewGen = " " . $task . " " . $previewGen;
        return $previewGen;
    }

    /*!
     \brief       Gets task information for movie tasks
     \param       $key       A task key from the taskID array
     \param       $task      A task from the taskID array
     \param       $srcImg    Whether the raw image or the deconvolved
     \param       $destDir   Whether to be saved in the source or the destination
     \param       $movieType Whether the movie is a stack, a time frame or SFP
     \return      The preview generation list
    */
    private function getImgProcessMovie($key,$task,$srcImg,$destDir,$movieType) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }
        
        $previewGen = "image ";
        $previewGen .= $this->getImageType($srcImg);
        $previewGen .= " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= $this->getThumbnailDestFile($srcImg,"Movie",$movieType);
        $previewGen .= " type ";
        $previewGen .= $this->getPreviewType($movieType);
        $previewGen .= " size ";
        $previewGen .= $this->getMovieSize();
        $previewGen = $this->string2tcllist($previewGen);
        $previewGen = " " . $task . " " . $previewGen;
        return $previewGen;
    }

    /*!
     \brief       Gets task information for stack comparison previews
     \param       $key     A task key from the taskID array
     \param       $task    A task from the taskID array
     \param       $destDir Whether to be saved in the source or the destination
     \return      The preview generation list
    */
    private function getImgProcessZComparison($key,$task,$destDir) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);

        if ($task == "") {
            return;
        }

        $previewGen = " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= $this->getThumbnailDestFile("","compareZStrips");
        $previewGen .= " type ";
        $previewGen .= $this->getPreviewType("compareZStrips");
        $previewGen .= " size ";
        $previewGen .= $this->getComparisonSize();
        $previewGen = $this->string2tcllist($previewGen);
        $previewGen = " " . $task . " " . $previewGen;
        return $previewGen;
    }

    /*!
     \brief       Gets task information for time frame comparison previews
     \param       $key     A task key from the taskID array
     \param       $task    A task from the taskID array
     \param       $destDir Whether to be saved in the source or the destination
     \return      The preview generation list
    */
    private function getImgProcessTComparison($key,$task,$destDir) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }

        $previewGen = " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= $this->getThumbnailDestFile("","compareTStrips");
        $previewGen .= " type ";
        $previewGen .= $this->getPreviewType("compareTStrips");
        $previewGen .= " size ";
        $previewGen .= $this->getComparisonSize();
        $previewGen = $this->string2tcllist($previewGen);
        $previewGen = " " . $task . " " . $previewGen;
        return $previewGen;
    }

    /*!
     \brief       Gets the preview type
     \return      The preview type
    */
    private function getPreviewType($type) {
        switch ( $type ) {
        case 'XYXZ':
        case 'orthoSlice':
        case 'compareZStrips':
        case 'ZMovie':
        case 'SFP':
        case 'compareTStrips':
        case 'timeSFPMovie':
        case 'timeMovie':
            break;
        default:
            error_log("Unknown preview type");
        }

        return $type;
    }

    /*!
     \brief       Gets the file suffix dependin on the movie type
     \param       $movieType  The movie type
     \return      The file suffix
    */
    private function getMovieFileSuffix($movieType) {
        switch ( $movieType ) {
        case 'ZMovie':
            $fileSuffix = ".stack";
            break;
        case 'timeSFPMovie':
            $fileSuffix = ".tSeries.sfp";
            break;
        case 'timeMovie':
            $fileSuffix = ".tSeries";
            break;
        default:
            error_log("Unknown movie type: $movieType");
        }
        return $fileSuffix;
    }

    /*!
     \brief       Gets the destination directory of a thumbnail
     \param       $destDir Source data or deconvolved data folder.
     \return      The thumbnail destination folder
    */
    private function getThumbnailDestDir($destDir) {
        if ($destDir == "dest") {
            $destDir = $this->getDestDir() . "/hrm_previews";
        } elseif ($destDir == "src") {
            $destDir = $this->getSrcDir() . "/hrm_previews";
        } else {
            error_log("Unknown image path: $destDir");
        }
        return $this->string2tcllist($destDir);
    }

    /*!
     \brief      Gets the destination file name of the thumbnail
     \param      $srcImg The image from which to make a thumbnail: raw or dec
     \param      $caller Which kind of thumbnail is to be made
     \param      $movieType Whether stack, timeMovie, timeSFPMovie or nothing.
     \return     A name for the thumbnail file
    */
    private function getThumbnailDestFile($srcImg,$caller,
                                          $movieType = null,
                                          $lif = null) {

        $thumbnail = $this->getThumbName();
        switch ( $caller ) {
        case 'XYXZ':
            if ($srcImg == "raw") {
                $destFile = basename($this->srcImage);
                $destFile .= $this->getLifImageSuffix($lif);
            } elseif ($srcImg == "dec") {
                $destFile = $thumbnail;
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'Ortho':
            if ($srcImg == "raw") {
                $destFile = $thumbnail . ".original";
            } elseif ($srcImg == "dec") {;
                $destFile = $thumbnail;
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'SFP':
            if ($srcImg == "raw") {
                $destFile = $thumbnail . ".original.sfp";
            } elseif ($srcImg == "dec") {
                $destFile = $thumbnail . ".sfp";
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'Movie':
            if ($srcImg == "raw") {
                $destFile = $thumbnail;
            } elseif ($srcImg == "dec") {
                $destFile = $thumbnail;
                $destFile .= $this->getMovieFileSuffix($movieType);
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'compareZStrips':
            $destFile = $thumbnail;
            break;
        case 'compareTStrips':
            $destFile = $thumbnail;
            break;
        default:
            error_log("Unknown thumbnail caller: $caller");
        }
        $destFile = $this->string2tcllist($destFile);
        return $destFile;
    }

    /*!
     \brief      Gets whether the image is deconvolved or raw data
     \param      $image    Whether the image is 'raw' or 'dec'.
     \return     The Huygens template word for 'raw', 'dec'.
    */
    private function getImageType($image) {
        if ($image == "raw") {
            return $image;
        } elseif ($image == "dec") {
            $image = "deconvolved";
        } else {
            error_log("Unknown image type: $image");
        }
        return $image;
    }

    /*!
     \brief       Gets the lif subimage name between parenthesis as suffix
     \param       $lif Whether or not a lif image is being dealt with
     \return      The image suffix
    */
    private function getLifImageSuffix($lif) {
        if (isset($this->subImage) && $lif != null) {
            $suffix = " (";
            $suffix .= $this->tcllist2string($this->subImage);
            $suffix .= ")";                    
        } else {
            $suffix = "";
        }
        return $suffix;
    } 

    /* ------------------------------ Utilities -------------------------------- */

    /*
     \brief       Gets the source image format as coded in the HuCore confidence
     \brief       table. The file types of the source image are coded differently
     \brief       in HRM and the HuCore confidence level table. For example,
     \brief       HRM codes tiff-leica for what the HuCore confidence table codes
     \brief       leica. This function does the mapping.
     \return      The src image format as coded in the confidence table of HuCore.
    */
    private function getHuImageFormat( ) {
        $microSetting = $this->microSetting;
        $format = $microSetting->parameter("ImageFileFormat")->value();

        switch ( $format ) {
        case 'ics':
            break;
        case 'ics2':
            $format = "ics";
            break;
        case 'hdf5':
            break;
        case 'dv':
            $format = "r3d";
            break;
        case 'ims':
            break;
        case 'lif':
            break;
        case 'lsm':
            break;
        case 'lsm-single':
            $format = "lsm";
            break;
        case 'ome-xml':
            $format = "ome";
            break;
        case 'pic':
            break;
        case 'stk':
            break;
        case 'tiff':
            break;
        case 'tiff-leica':
            $format = "leica";
            break;
        case 'tiff-series':
            $format = "leica";
            break;
        case 'tiff-single':
            $format = "tiff";
            break;
        case 'zvi':
            break;
        default:
            error_log("Unknown image format: $format");
            $format = "";
            break;
        }

        return $format;
    }

    /*!
     \brief       Constructs a shorter basic name for the thumbnails based on
     \brief       the job id.
     \return      The thumbnail name.
     */
    private function getThumbName( ) {
        $basename = basename($this->destImage);
        $filePattern = "/(.*)_(.*)_(.*)\.(.*)$/";
        preg_match($filePattern,$basename,$matches);
        return $matches[2] . "_" . $matches[3] . "." . $matches[4];
    }

    /*!
     \brief       Gets the parameter confidence level
     \param       $paramName The parameter name.
     \param       $channel The channel number.
     \return      The confidence level. 
    */
    private function getParameterConfidence($paramName,$channel) {

        /* If the parameter has a value it means that the parameter was 
         introduced by the user. That makes the parameter automatically 
         verified.*/
        $parameterValue = $this->getParameterValue($paramName,$channel);
        if ($parameterValue != "*") {
            return "noMetaData";
        } else {
            return "default";
        }
    }

    private function getParameterValue($paramName,$channel) {

        switch ( $paramName ) {
        case 'MicroscopeType':
            $parameterValue = $this->getMicroTypeForChannel($channel);
            break;
        case 'PinholeSize':
            $parameterValue = $this->getPinRadiusForChannel($channel);
            break;
        case 'CoverslipRelativePosition':
            $parameterValue = $this->getImagingDirForChannel($channel);
            break;
        case 'PinholeSpacing':
            $parameterValue = $this->getPinSpacingForChannel($channel);
            break;
        case 'ObjQuality':
            $parameterValue = $this->getObjQualityForChannel($channel);
            break;
        case 'ExcitationPhoton':
            $parameterValue = $this->getExcitationPcntForChannel($channel);
            break;
        case 'ExcitationWavelength':
            $parameterValue = $this->getExLambdaForChannel($channel);
            break;
        case 'EmissionWavelength':
            $parameterValue = $this->getEmLambdaForChannel($channel);
            break;
        case 'ExBeamFactor':
            $parameterValue = $this->getExBeamForChannel($channel);
            break;
        case 'SampleMedium':
            $parameterValue = $this->getMRIndexForChannel($channel);
            break;
        case 'ObjectiveType':
            $parameterValue = $this->getLensRIForChannel($channel);
            break;
        case 'NumericalAperture':
            $parameterValue = $this->getNumApertureForChannel($channel);
            break;
        case 'TimeInterval':
            $parameterValue = $this->getSamplingSizeT();
            break;
        case 'ZStepSize':
            $parameterValue = $this->getSamplingSizeZ();
            break;
        case 'CCDCaptorSizeX':
            $parameterValue = $this->getSamplingSizeX();
            break;
        default:
            $parameterValue = "";
        }

        if ($parameterValue == "" || $parameterValue == "{}") {
            $parameterValue = "*";
        }

        return $parameterValue;
    }


    /*!
     \brief       Gets options for the 'algorithm' task. All channels.
     \return      Deconvolution 'algorithm' task string and its options.
    */
    private function getImgProcessAlgorithms( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $algorithms = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $algorithm = $this->getAlgorithm($chanCnt);
            $algOptions = $this->getTaskAlgorithm($chanCnt);
            $algorithms .= " ${algorithm}:$chanCnt $algOptions";
        }

        return $algorithms;
    }


    /*!
     \brief       Get the Huygens task name of a task.
     \param       $key A task array key
     \param       $task A task compliant with the Huygens template task names
     \return      The task name (includes channel number, preview number, etc.)
    */
    private function parseTask($key,$task) {

        if ($task == "" && $key == "algorithms") {
            $task = $this->parseAlgorithm();
        } elseif ($task == "previewGen") {
            $task = $this->parsePreviewGen($key,$task);
        } else {
            return $task;
        }

        return $task;
    }

    /*!
     \brief       Gets the Huygens task name of a preview task
     \param       $key A task array key
     \param       $task A task compliant with the Huygens template task names
     \return      The Huygens preview task name
    */
    private function parsePreviewGen($key,$task) {
        global $useThumbnails;
        global $saveSfpPreviews;
        global $maxComparisonSize;

        $maxPreviewPixelsPerDim = 65000;

        if (!$useThumbnails) {
            return;
        } else {
            if (strstr($key, 'SFP')) {
                if ($saveSfpPreviews) {
                    $task = $task . ":" . $this->thumbCnt;
                    $this->thumbCnt++;
                } else {
                    $task = "";
                }
            } elseif (strstr($key, 'Lif')) {
                if ($this->isImageLif()) {
                    $task = $task . ":" . $this->thumbCnt;
                    $this->thumbCnt++;
                } else {
                    $task = "";
                }
            } elseif (strstr($key, 'ZComparison')) {

                if ($this->sizeX < $maxComparisonSize) {
                    $previewPixelsX = 2 * $this->sizeX;
                } else {
                    $previewPixelsX = 2 * $maxComparisonSize;
                }

                if ($this->sizeY < $maxComparisonSize) {
                    $previewPixelsY = $this->sizeY * $this->sizeZ;
                } else {
                    $previewPixelsY = $maxComparisonSize * $this->sizeZ;
                }

                # Check whether the comparison strip might get dangerously big.
                if ($previewPixelsX < $maxPreviewPixelsPerDim 
                    && $previewPixelsY < $maxPreviewPixelsPerDim) {
                    $task = $task . ":" . $this->thumbCnt;
                    $this->thumbCnt++;
                    $this->compareZviews = TRUE;
                } else {
                    $task = "";
                    $this->compareZviews = FALSE;
                }
            } elseif (strstr($key, 'TComparison')) {

                if ($this->sizeX < $maxComparisonSize) {
                    $previewPixelsX = 2 * $this->sizeX;
                } else {
                    $previewPixelsX = 2 * $maxComparisonSize;
                }

                if ($this->sizeY < $maxComparisonSize) {
                    $previewPixelsY = $this->sizeY * $this->sizeT;
                } else {
                    $previewPixelsY = $maxComparisonSize * $this->sizeT;
                }

                # Check whether the comparison strip might get dangerously big.
                if ($previewPixelsX < $maxPreviewPixelsPerDim 
                    && $previewPixelsY < $maxPreviewPixelsPerDim) {
                    $task = $task . ":" . $this->thumbCnt;
                    $this->thumbCnt++;
                    $this->compareTviews = TRUE;
                } else {
                    $task = "";
                    $this->compareTviews = FALSE;
                }
            } else {
                $task = $task . ":" . $this->thumbCnt;
                $this->thumbCnt++;
            }
        } 
        return $task;
    }

    /*!
     \brief       Gets the Huygens deconvolution task names of every channel
     \return      The Huygens deconvolution task names
    */
    private function parseAlgorithm ( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $algorithms = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $algorithms .= $this->getAlgorithm().":$chanCnt ";
        }
        return trim($algorithms);
    }

    /*!
     \brief       Gets the task tag information
     \return      The Tcl-compliant nested list with the tag information
    */
    private function getTaskTag( ) {
        /* There are no specific names for the deconvolution and microscopic
         templates in the Tcl-lists, they will be set to general names. */
        $taskTag = "setp microscopicTemplate decon deconvolutionTemplate";
        return  $this->string2tcllist($taskTag);
    }

    /*
     \brief       Gets the open image series mode fo time series.
     \return      The series mode: whether auto or off.
    */
    private function getSeriesMode( ) {
        $microSetting = $this->microSetting;
        $imageGeometry = $microSetting->parameter("ImageGeometry")->value();
 
        $srcInfo = pathinfo($this->srcImage);        
        if ((preg_match("/stk/i",$srcInfo['extension'])
             || preg_match("/tif/i",$srcInfo['extension']))
            && preg_match("/time/",$imageGeometry)) {
            $seriesMode = "auto";
        } else {
            $seriesMode = "off";
        }
        return $seriesMode;
    }

    /*!
     \brief       Whether or not an image has lif extension. 
     \brief       Not very elegant, but it is here convenient to check whether
     \brief       image is lif through the presence of subimages.
     \return      Boolean 
    */
    private function isImageLif( ) {
        if (isset($this->subImage)) {
            return true;
        } else {
            return false;
        }
    }
    
    /*!
     \brief       Gets size of the thumbnails.
     \return      Number: The thumbnail size.
    */
    private function getPreviewSize( ) {
        return 400;
    }

    /*!
     \brief       Gets size of the movie.
     \return      Number: The movie size.
    */
    private function getMovieSize( ) {
        global $movieMaxSize;
        return $movieMaxSize;
    }

    /*!
     \brief       Gets comparison size.
     \return      Number: The comparison size.
    */
    private function getComparisonSize( ) {
        global $maxComparisonSize;
        return $maxComparisonSize;
    }

    /*!
     \brief       Gets the current date in format: Wed Feb 02 16:02:11 CET 2011
     \return      The date
    */
    private function getTemplateDate( ) {
        $today = date("D M j G:i:s T Y");  
        $today = $this->string2tcllist($today);
        return $today;
    }

    /*!
     \brief       Gets the template name as batch_2011-02-02_16-02-08
     \return      The template name
    */
    private function getTemplateName( ) {
        $time = date('h-i-s');  
        $today = date('Y-m-d');  
        $templateName = "batch_" . $today . "_" . $time;
        return $templateName;
    }

    /*!
     \brief       Gets value for the multidir export format option.
     \return      A boolean with the multidir option value.
    */
    private function getMultiDirOpt( ) {
        $outputType = $this->getOutputFileType();

        if (preg_match("/tif/i",$outputType)) {
            return 1;
        } else {
            return 0;
        }
    }

    /*!
     \brief       Gets the number of channels of the template.
     \return      Number of channels.
    */
    private function getNumberOfChannels( ) {
        return $this->microSetting->numberOfChannels();
    }

    /*!
     \brief       Wraps a string between curly braces to turn it into a Tcl list.
     \param       $string A string
     \return      A Tcl list.
    */
    private function string2tcllist($string) {
        $tcllist = trim($string);
        $tcllist = "{{$tcllist}}";
        return $tcllist;
    }

    /*!
     \brief       Removes the wrapping of a Tcl list to leave just a string.
     \param       $tcllist A string acting as Tcl list
     \return      A string.
    */
    private function tcllist2string($tcllist) {
        $string = str_replace("{","",$tcllist);
        $string = str_replace("}","",$string);
        return $string;
    }

    /*!
     \brief       Sets the name of the source image with subimages if any.
    */
    private function setSrcImage( ) {
        $this->srcImage = $this->jobDescription->sourceImageName();

        /*If a (string) comes after the file name, the string is interpreted
         as a subimage. Currently this is for LIF files only. */
        if ( preg_match("/^(.*\.lif)\s\((.*)\)/i", $this->srcImage, $match) ) {
            $this->srcImage = $match[1];
            $this->subImage = $match[2];
        }
    }

    /*!
     \brief       Gets the name of the source image.
     \return      The name of the source image.
    */
    private function getSrcImage( ) {
        return $this->srcImage;
    }

    /*!
     \brief       Gets the directory of the source image.
     \return      A file path.
    */
    private function getSrcDir( ) {
        $srcFileName = $this->getSrcImage();
        return dirname($srcFileName);
    }

    /*!
     \brief       Sets the name of the destination image.
    */
    private function setDestImage ( ) {
        $this->destImage = $this->jobDescription->destinationImageFullName();
        $fileType = $this->getOutFileExtension();
        $this->destImage = $this->destImage.".".$fileType;
    }

    private function getDestImageBaseName( ) {
        $destInfo = pathinfo($this->destImage);
        return basename($this->destImage,'.'.$destInfo['extension']);
    }

    /*!
     \brief       Sets the image format as coded in the HuCore confidence level
     \brief       table, whether leica, r3d, etc.
    */
    private function setHuImageFormat( ) {
        $this->HuImageFormat = $this->getHuImageFormat();
    }

    /*!
     \brief      Sets x, y, z, t and channel dimensions.
    */
    private function setImageDimensions( ) {

        /* Get file path, name and time series option */
        $pathInfo = pathinfo($this->srcImage);
        $path = $pathInfo['dirname'];
        $filename = $pathInfo['basename'];
        $series = $this->getSeriesMode();
        $opt = "-path $path -filename \"$filename\" -series $series";

        /* Retrieve the image dimensions */
        $result = askHuCore( "reportImageDimensions", $opt );
        $this->sizeX = $result['sizeX'];
        $this->sizeY = $result['sizeY'];
        $this->sizeZ = $result['sizeZ'];
        $this->sizeT = $result['sizeT'];
        $this->sizeC = $result['sizeC'];
    }

    /*!
     \brief       Gets the directory of the destination image.
     \return      A file path.
    */
    private function getDestDir( ) {
        return dirname($this->destImage);
    }

    /*!
     \brief       Gets the file type of the destination image.
     \return      A file type: whether imaris, ome, etc.
    */
    private function getOutputFileType( ) {
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->translatedValue();
    }

    /*!
     \brief       Gets the file extension of the destination image.
     \return      A file extension: whether ims, tif, etc.
    */
    private function getOutFileExtension( ) {
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->extension();
    }


    /* ------------------------------------------------------------------------- */
}

?>