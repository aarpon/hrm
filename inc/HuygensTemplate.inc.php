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
     \var     $template
     \brief   Batch template containing the deconvolution job + thumbnail tasks.
    */
    public $template;

    /*!
      \var    $jobInfoArray
      \brief  Array with informaton on the template job info tag.
    */
    private $jobInfoArray;

    /*!
     \var     $jobInfoList
     \brief   A Tcl list with job information for the template info tag.
     */
    private $jobInfoList;
    
    /*!
      \var    $jobTasksArray
      \brief  Array with information on the job's main tasks.
    */
    private $jobTasksArray;

    /*!
     \var     $jobTasksList
     \brief   A Tcl list with information on the job's main tasks.
    */
    private $jobTasksList;
    
    /*!
      \var    $envArray
      \brief  Array with information on the setEnv task.
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
     \var     $imgProcessList
     \brief   A Tcl list with restoration and thumbnail operations.
    */
    private $imgProcessList;

    /*!
     \var     $imgProcessInfoArray
     \brief   Array with information on the info tag of the img process task.
    */
    private $imgProcessInfoArray;

    /*!
     \var     $imgProcessTasksArray
     \brief   Array with information on the subtasks of the img process task.
    */
    private $imgProcessTasksArray;

    /*!
     \var     $expFormatArray
     \brief   Array with information on export format tag of the setenv task.
    */
    private $expFormatArray;

    /*!
     \var    $imgOpenArray
     \brief  Array with information on the image open subtask.
    */
    private $imgOpenArray;

    /*!
     \var    $imgSaveArray
     \brief  Array with information on the image save subtask.
    */
    private $imgSaveArray;

    /*!
     \var    $autocropArray
     \brief  Array with information on the autocrop subtask.
    */
    private $autocropArray;

    /*!
     \var    $ZStabilizeArray
     \brief  Array with information on the Z stabilize subtask.
    */
    private $ZStabilizeArray;

    /*!
     \var    $adjblArray;
     \brief  Array with information on the image adjbl subtask.
    */
    private $adjblArray;
    
    /*!
     \var    $chromaticArray;
     \brief  Array with information on the image chromatic aberration subtask.
    */
    private $chromaticArray;

    /*!
     \var    $algArray;
     \brief  Array with information on the image cmle/qmle subtask.
    */
    private $algArray;

    /*!
     \var    $colocArray;
     \brief  Array with information on the colocalization analysis subtask.
    */
    private $colocArray;

    /*!
     \var    $histoArray;
     \brief  Array with information on the 2D histogram subtask.
    */
    private $histoArray;

    /*!
      \var    $setpArray
      \brief  Array with information on the set parameter 'setp' subtask.
    */
    private $setpArray;

    /*!
      \var    $setpConfArray
      \brief  Array with information on the parameter confidence levels.
    */
    private $setpConfArray;

    /*!
      \var    $thumbArray
      \brief  Array with information on thumbnail projections.
    */
    private $thumbArray;

    /*!
      \var    $setpList
      \brief  A Tcl list with information for the 'set parameter' subtask.
    */
    private $setpList;

    /*!
      \var    $srcImage
      \brief  Path and name of the source 'raw' image.
    */
    private $srcImage;

    /*!
      \var    $destImage
      \brief  Path and name of the deconvolved image.
    */
    private $destImage;

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
      \var    $analysisSetting
      \brief  An AnalysisSetting object: unformatted analysis parameters.
    */
    private $analysisSetting;

    /*!
     \var     $compareZviews
     \brief   A boolean to know whether a Z slicer will be created.
    */
    public $compareZviews;

    /*!
     \var     $compareTviews
     \brief   A boolean to know whether a T slicer will be created.
    */
    public $compareTviews;

    /*! 
     \var     $thumbCnt
     \brief   An integer that keeps track of the number of thumbnail tasks.
    */
    private $thumbCnt;

    /*! 
     \var     $thumbFrom
     \brief   Whether to make a thumbnail from the raw or from the decon image.
    */
    private $thumbFrom;
    
    /*! 
     \var     $thumbToDir
     \brief   Whether the thumbnail is to be saved in the src or dest folder.
    */
    private $thumbToDir;

    /*! 
     \var     $thumbType
     \brief   Whether to make an XYXZ, Ortho, SFP, Movie, etc, type of thumbnail.
    */
    private $thumbType;

    /*! 
     \var     $thumbSubImg
     \brief   Whether to make thumbnails for lif and czi sub images.
    */
    private $thumbSubImg;

    /* -------------------------- Constructor ------------------------------- */

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
     \param       $jobDescription JobDescription object
    */
    private function initialize($jobDescription) {
        $this->jobDescription  = $jobDescription;
        $this->microSetting    = $jobDescription->parameterSetting;
        $this->deconSetting    = $jobDescription->taskSetting;
        $this->analysisSetting = $jobDescription->analysisSetting;

        $this->initializeImg();
        $this->initializeThumbCounter();
        $this->initializeJobInfo();
        $this->initializeJobTasks();
        $this->initializeEnvironment();
        $this->initializeImgProcessing();
    }

    /*!
     \brief       Sets names and paths of the raw and deconvolved images.
    */
    private function initializeImg( ) {
        $this->setSrcImage();
        $this->setDestImage();
    }

    /*!
     \brief       Resets the counter of thumbnail tasks
    */
    private function initializeThumbCounter( ) {
        $this->thumbCnt = 0;
    }

    /*!
     \brief       Loads an array containing information on the job info tag.
    */
    private function initializeJobInfo() {

        $this->jobInfoArray = 
            array ('title'                      => 'Batch Processing template',
                   'version'                    => '2.3',
                   'templateName'               => '',
                   'date'                       => '',
                   'listID'                     => 'info');
    }

    /*!
     \brief       Loads an array containing information on the job's main tasks.
    */
    private function initializeJobTasks() {
        
        $this->jobTasksArray = 
            array ( 'setEnv'                    => '',
                    'taskID:0'                  => '',
                    'listID'                    => 'taskList' );
    }

    /*!
     \brief       Loads arrays with environment data: number cores, timeout, ..
    */
    private function initializeEnvironment( ) {
        $this->envArray = 
            array ( 'resultDir'                 => '',
                    'perJobThreadCnt'           => 'auto',
                    'concurrentJobCnt'          => '1',
                    'OMP_DYNAMIC'               => '1',
                    'timeOut'                   => '10000',
                    'exportFormat'              => '',
                    'attemptGpu'                => '',
                    'listID'                    => 'setEnv' );

        $this->expFormatArray = 
            array ( 'type'                      =>  '',
                    'multidir'                  =>  '',
                    'cmode'                     =>  'scale' );
    }

    /*!
     \brief       Loads arrays with the job's tasks and subtasks.
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

        /* There are no specific names for the deconvolution and microscopy
         templates in the Tcl-lists, they will be set to general names. */
        $this->imgProcessInfoArray = 
            array ( 'state'                     => 'readyToRun',
                    'tag'                       => '{setp Micr decon Decon}',
                    'timeStartAbs'              => '',
                    'timeOut'                   => '10000',
                    'userDefConfidence'         => 'default',
                    'listID'                    => 'info' );

        /* These are the operations carried out by HuCore/HRM. Operations for
           thumbnail generation are included. Notice that the names of the
           thumbnail operations contain the destination directory as well as
           the thumbnail type and the image type. The thumbnail operation
           names code the type of action executed on the image.*/
        $this->imgProcessTasksArray = 
            array ('open'                       =>  'imgOpen',
                   'setParameters'              =>  'setp',
                   'autocrop'                   =>  'autocrop',
                   'adjustBaseline'             =>  'adjbl',
                   'ZStabilization'             =>  'stabilize',
                   'algorithms'                 =>  '',
                   'colocalization'             =>  'coloc',
                   'chromatic'                  =>  'shift',
                   '2Dhistogram'                =>  'hist',
                   'XYXZRawAtSrcDir'            =>  'previewGen',
                   'XYXZRawSubImgAtSrcDir'      =>  'previewGen',
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

        /* Options for the 'open image' action */
        $this->imgOpenArray = 
            array ( 'path'                      =>  '',
                    'subImg'                    =>  '',
                    'series'                    =>  '',
                    'index'                     =>  '0',
                    'listID'                    =>  'imgOpen' );

        /* Options for the 'set image parameter' action */
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
                    'stedMode'                  => '',
                    'stedLambda'                => '',
                    'stedSatFact'               => '',
                    'stedImmunity'              => '',
                    'sted3D'                    => '',
                    'listID'                    => 'setp' );

        /* Options for the 'set image pararmeter' action */
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
                    'stedMode'                  =>  'parState,stedMode',
                    'stedLambda'                =>  'parState,stedLambda',
                    'stedSatFact'               =>  'parState,stedSatFact',
                    'stedImmunity'              =>  'parState,stedImmunity',
                    'sted3D'                    =>  'parState,sted3D',
                    'listID'                    =>  'setp' );

        /* Options for the 'adjust baseline' action */
        $this->adjblArray = 
            array ( 'enabled'                   =>  '0',
                    'ni'                        =>  '0',
                    'listID'                    =>  'adjbl' );

        /* Options for the 'chromatic aberration correction' action */
        $this->chromaticArray = 
            array ( 'q'                         =>  'standard',
                    'vector'                    =>  '',
                    'reference'                 =>  '',
                    'channel'                   =>  '',
                    'lambdaEm'                  =>  '480',
                    'lambdaEx'                  =>  '480',
                    'lambdaSted'                =>  '480',
                    'mType'                     =>  'generic',
                    'estMethod'                 =>  '2',
                    'listID'                    =>  'shift');

        /* Options for the 'execute deconvolution' action */
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

        /* Options for the 'autocrop' action. */
        $this->autocropArray =
            array( 'enabled'                    =>  '0',
                   'listID'                     =>  'autocrop');

        /* Options for the 'ZStabilization' action. */
        $this->ZStabilizeArray =
            array( 'enabled'                    =>  '0',
                   'listID'                     =>  'stabilize');

        /* Options for the 'colocalization analysis' action. */
        $this->colocArray  =
            array( 'chanR'                      =>  '',
                   'chanG'                      =>  '',
                   'threshMode'                 =>  '',
                   'threshPercR'                =>  '',
                   'threshPercG'                =>  '',
                   'coefficients'               =>  '',
                   'map'                        =>  '',
                   'destDir'                    =>  '',
                   'destFile'                   =>  '',
                   'listID'                     =>  'coloc' );

            /* Options for the '2D histogram' action. */
        $this->histoArray  =
            array( 'chanR'                      =>  '',
                   'chanG'                      =>  '',
                   'destDir'                    =>  '',
                   'destFile'                   =>  '',
                   'listID'                     =>  'hist' );        

        /* Options for the 'create thumbnail from image' action. */
        $this->thumbArray =
            array( 'image'                      =>  '',
                   'destDir'                    =>  '',
                   'destFile'                   =>  '',
                   'type'                       =>  '',
                   'size'                       =>  '400' );
        
        /* Options for the 'save image' action. */
        $this->imgSaveArray = 
            array ( 'rootName'                  =>  '',
                    'listID'                    =>  'imgSave' );

        /* Check whether the image is manageable to create slices from it. */
        $this->isEligibleForSlicers($this->srcImage);
    }

    /* --------------------- Main task list builders ------------------------- */

    /*!
     \brief       Puts the Huygens Batch template together.
    */
    private function assembleTemplate( ) {
        $this->template =  $this->jobInfoList . "\n";
        $this->template .= $this->jobTasksList . "\n";
        $this->template .= $this->envList . "\n ";
        $this->template .= $this->imgProcessList;
    }

    /*!
     \brief       Sets the template info tag.
    */
    private function setJobInfoList( ) {
        
        $list = "";

        foreach ($this->jobInfoArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }
            
            switch ( $key ) {
            case 'version':
                $list .= $value;
                break;
            case 'title':
                $list .= $this->string2tcllist($value);
                break;
            case 'templateName':
                $list .= $this->getTemplateName();
                break;
            case 'date':
                $list .= $this->getTemplateDate();
                break;
            case 'listID':
                $list = $this->string2tcllist($list);
                $this->jobInfoList = $value . " " . $list;
                break;
            default:
                error_log("Job info field $key not yet implemented.");       
            }
        }
    }

    /*!
     \brief       Sets the template's main tasks: setEnv and taskIDs.
    */
    private function setJobTasksList() {

        $list = "";

        foreach ($this->jobTasksArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }
            
            switch ( $key ) {
            case 'setEnv':
            case 'taskID:0':
                break;
            case 'listID':
                $list = $this->string2tcllist($list);
                $this->jobTasksList = $value . " " . $list;       
                break;
            default:
                error_log("Job task $key not yet implemented.");
            }
        }
    }

    /*!
     \brief      Sets the job's environment task: number of cores, timeout, etc. 
    */
    private function setEnvList( ) {

        $list = "";

        foreach ($this->envArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }

            switch ( $key ) {
            case 'resultDir':
                $list .= $this->string2tcllist($this->getDestDir());
                break;
            case 'exportFormat':
                $list .= $this->getExportFormat();
                break;
            case 'attemptGpu':
                $db = new DatabaseConnection();
                $env .= $db->getGpuStateAsString();
                break;
            case 'listID':
                $list = $this->string2tcllist($list);
                $this->envList = $value . " " . $list;
                break;
            case 'perJobThreadCnt':
            case 'concurrentJobCnt':
            case 'OMP_DYNAMIC':
            case 'timeOut':
                $list .= $value;
                break;
            default:
                error_log("Environment field $key not yet implemented");
            }
        }
    }
    
    /*!
     \brief       Sets the template's restoration and thumbnail operations.
    */
    private function setImgProcessList( ) {
        
        $list = "";
        
        foreach ($this->imgProcessArray as $key => $value) {
            
            if ($key != "listID") {
                $list .= " ";
            }
          
            switch ( $key ) {
            case 'info':
                $list .= $this->getImgProcessInfoList();
                break;
            case 'taskList':
                $list .= $this->getImgProcessTaskList();
                $list .= $this->getImgProcessTasksDescr();
                break;
            case 'listID':
                $list = $this->string2tcllist($list);
                $this->imgProcessList = $value . " " .$list;
                break;
            default:
                error_log("Image processing task $key not yet implemented");
            }
        }
    }

    /* ------------------- Secondary task list builders ---------------------- */
    
    /*!
     \brief       Gets information on the template's only job.
     \return      The Tcl-compliant nested list with the info details.
    */
    private function getImgProcessInfoList( ) {

        $list = "";
        
        foreach ($this->imgProcessInfoArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }

            switch( $key ) {
            case 'timeOut':
            case 'tag':
            case 'state':
            case 'userDefConfidence':
                $list .= $value;
                break;
            case 'timeStartAbs':
                $list .= time();
                break;                
            case 'listID':
                $list = $this->string2tcllist($list);
                $list = $value . " " . $list;
                break;
            default:
                error_log("Info option $key not yet implemented");
            }
        }
        
        return $list;
    }

    /*!
     \brief       Gets the Huygens subtask names of the deconvolution process.
     \return      The Tcl-compliant nested list with subtask names.
    */
    private function getImgProcessTaskList( ) {
        
        $list = "";
        
        foreach ($this->imgProcessTasksArray as $key => $value) {
            switch ( $key ) {
            case 'open':
            case 'save':
            case 'setParameters':
            case 'autocrop':
            case 'adjustBaseline':
            case 'ZStabilization':
            case 'algorithms':
            case 'chromatic':
            case 'colocalization':
            case '2Dhistogram':    
            case 'XYXZRawAtSrcDir':
            case 'XYXZRawSubImgAtSrcDir':
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
                $task = $this->getTaskName($key,$value);
                if ($task != "") {
                    $list .= $task ." ";
                }
                break;
            case 'listID':
                $list = $this->string2tcllist($list);
                $list = $value . " " . $list;
                break;
            default:
                error_log("Image process task $key not yet implemented");
            }
        }
        
        return $list;
    }

    /*!
     \brief       Gets details of specific deconvolution subtasks. 
     \return      The Tcl-compliant nested list with subtask details.
    */
    private function getImgProcessTasksDescr( ) {

        $tasksDescr = "";
        
        $this->initializeThumbCounter();
        foreach ($this->imgProcessTasksArray as $key => $value) { 
            $tasksDescr .= " ";
            switch ( $key ) {
            case 'open':
                $tasksDescr .= $this->getImgTaskDescrOpen();
                break;
            case 'save':
                $tasksDescr .= $this->getImgTaskDescrSave();
                break;
            case 'setParameters':
                $tasksDescr .= $this->getImgTaskDescrSetp();
                break;
            case 'autocrop':
                $tasksDescr .= $this->getImgTaskDescrAutocrop();
                break;
            case 'adjustBaseline':
                $tasksDescr .= $this->getImgTaskDescrAdjbl();
                break;
            case 'ZStabilization':
                $tasksDescr .= $this->getImgTaskDescrZStabilize();
                break;
            case 'algorithms':
                $tasksDescr .= $this->getImgTaskDescrAlgorithms();
                break;
            case 'chromatic':
                $tasksDescr .= $this->getImgTaskDescrChromatic();
                break;
            case 'colocalization':
                $tasksDescr .= $this->getImgTaskDescrColocs();
                break;
            case '2Dhistogram':
                $tasksDescr .= $this->getImgTaskDescrHistograms();
                break;
            case 'XYXZRawAtSrcDir':
            case 'XYXZRawSubImgAtSrcDir':
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
                $tasksDescr .= $this->getImgTaskDescrThumbnail($key,$value);
                break;
            case 'listID':
                break;
            default:
                $tasksDescr = "";
                error_log("Image processing task $key not yet implemented.");
            }
        }

        return $tasksDescr;
    }
    
    /*!
     \brief       Gets options for the 'image open' task.
     \return      Tcl list with the'Image open' task and its options.
    */
    private function getImgTaskDescrOpen( ) {

        $taskDescr = "";
        
        foreach ($this->imgOpenArray as $key => $value) {

           if ($key != "subImg" && $key != 'listID') {
                $taskDescr .= " " . $key . " ";
            }

            switch( $key ) {
            case 'path':
                $taskDescr .= $this->string2tcllist($this->srcImage);
                break;
            case 'series':
                $taskDescr .= $this->getSeriesMode();
                break;
            case 'index':
                $taskDescr .= " " . $value . " ";
                break;
            case 'subImg':
                if (isset($this->subImage)) {
                    $taskDescr .= " " . $key . " ";
                    $taskDescr .= $this->string2tcllist($this->subImage);
                }
                break;
            case 'listID':
                $taskDescr = $this->string2tcllist($taskDescr);
                $taskDescr = $value  . " " . $taskDescr;
                break;
            default:
                error_log("Image open option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /*!
     \brief       Gets options for the 'set parameters' task.
     \return      Tcl list with the 'Set parameters' task and its options.
    */
    private function getImgTaskDescrSetp( ) {

        $taskDescr = "";
        
        foreach ($this->setpArray as $key => $value) { 

            switch ( $key ) {
            case 'completeChanCnt':
                $taskDescr .= $key . " " . $this->getChanCnt();
                break;
            case 'ps':
            case 'pr':
            case 'micr':
            case 's':
            case 'imagingDir':
            case 'objQuality':
            case 'pcnt':
            case 'ex':
            case 'em':
            case 'exBeamFill':
            case 'ri':
            case 'ril':
            case 'na':
            case 'iFacePrim':
            case 'iFaceScnd':
            case 'stedMode':
            case 'stedLambda':
            case 'stedSatFact':
            case 'stedImmunity':
            case 'sted3D':
                break;
            case 'listID':
                $taskDescr = $this->string2tcllist($taskDescr);
                $this->setpList = $value . " " . $taskDescr;
                break;
            default:
                error_log("Setp field $key not yet implemented.");       
            }

            if ($key != "listID" && $key != "completeChanCnt") {
                $taskDescr .= $this->getParameter($key,$value);
            }
        }

        return $this->setpList;
    }

    /*!
     \brief       Gets options for the 'adjust baseline' task.
     \return      Tcl list with the 'Adjust baseline' task and its options.
    */
    private function getImgTaskDescrAdjbl( ) {

        $taskDescr = "";
        
        foreach ($this->adjblArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch( $key ) {
            case 'ni':
            case 'enabled':
                $taskDescr .= $value;
                break;
            case 'listID':
                $taskDescr = $this->string2tcllist($taskDescr);
                $taskDescr = $value . " " . $taskDescr;
                break;
            default:
                error_log("Image adjbl option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }
    
    /*!
     \brief       Gets options for the 'chromatic aberration correction' task.
     \return      Tcl list with the 'chromatic aberration' task and its options.
    */
    private function getImgTaskDescrChromatic( ) {
        
        $allTasksDescr = "";
        
        $channelsArray = $this->getChansForChromaticCorrection();
        if (empty($channelsArray)) {
            return $allTasksDescr;
        }

        $chromaticParam = $this->deconSetting->parameter("ChromaticAberration");
        foreach ($channelsArray as $chanKey => $chan) {
            $taskDescr = "";
            $chanVector = implode(' ', $chromaticParam->chanValue( $chan ));
            
            foreach ($this->chromaticArray as $chromKey => $chromValue) {
                if ($chromKey != "listID") {
                    $taskDescr .= " " . $chromKey . " ";
                }

                /* Notice that we force a 'sorted' channel correction, i.e.,
                   there's no matching done based on wavelengths, etc. */ 
                switch( $chromKey ) {
                case 'q':
                case 'lambdaEm':
                case 'lambdaEx':
                case 'lambdaSted':
                case 'mType':
                case 'estMethod':
                    $taskDescr .= $chromValue;
                    break;
                case 'channel':
                    $taskDescr .= $chan;
                    break;
                case 'vector':
                    $taskDescr .= $this->string2tcllist($chanVector);
                    break;
                case 'reference':
                    if ($chanVector == "0 0 0 0 1") {
                        $reference = 1;
                    } else {
                        $reference = 0;
                    }
                    $taskDescr .= $reference;
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $allTasksDescr .= $chromValue . ":$chanKey ";
                    $allTasksDescr .= $taskDescr . " ";
                    break;
                default:
                    error_log("Image shift option $chromKey not yet implemented.");
                }
            }
        }
        
        return $allTasksDescr;
    }

    /*!
      \brief      Get options for the 'Autocrop' task. 
      \return     Tcl list with the 'autocrop' task and its options.
    */
    private function getImgTaskDescrAutocrop( ) {
        
        $taskDescr = "";

        $autocropParam = $this->deconSetting->parameter('Autocrop');
        foreach ($this->autocropArray as $key => $value) {
            
            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch( $key ) {
            case 'enabled':
                $taskDescr .= $autocropParam->value();
                break;
            case 'listID':
                $taskDescr = $this->string2tcllist($taskDescr);
                $taskDescr = $value . " " . $taskDescr;
                break;
            default:
                error_log("Image autocrop option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }
    
    /*!
      \brief      Get options for the 'ZStabilize' task. 
      \return     Tcl list with the 'ZStabilize' task and its options.
    */
    private function getImgTaskDescrZStabilize( ) {
        
        $taskDescr = "";

        $stedData = False;
        $chanCnt = $this->getChanCnt();
        for($chan = 0; $chan < $chanCnt; $chan++) {
            if (strstr($this->getMicroscopeType($chan),'sted')) {
                $stedData = True;
                break;
            }
        }

        $ZStabilizeParam = $this->deconSetting->parameter('ZStabilization');
        foreach ($this->ZStabilizeArray as $key => $value) {
            
            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            /* Stabilization should only be applied to STED data. */
            switch( $key ) {
            case 'enabled':
                if ($stedData) {
                    $taskDescr .= $ZStabilizeParam->value();
                } else {
                    $taskDescr .= '0';
                }
                break;
            case 'listID':
                $taskDescr = $this->string2tcllist($taskDescr);
                $taskDescr = $value . " " . $taskDescr;
                break;
            default:
                error_log("Image Z stabilize option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /*!
     \brief       Gets options for the 'algorithm' task. All channels.
     \return      Deconvolution 'algorithm' task string and its options.
    */
    private function getImgTaskDescrAlgorithms( ) {

        $allTasksDescr = "";
        
        $chanCnt = $this->getChanCnt();
        for($chan = 0; $chan < $chanCnt; $chan++) {
            $algorithm = $this->getAlgorithm($chan);
            $taskDescr = $this->getTaskDescrAlgorithm($chan);
            $allTasksDescr .= " ${algorithm}:$chan $taskDescr";
        }

        return $allTasksDescr;
    }

    /*!
     \brief       Gets options for all the 'colocalization' tasks.
     \return      Tcl list with the 'colocalization' tasks and their options.
    */
    private function getImgTaskDescrColocs( ) {

        $allTasksDescr = "";
        
        if (!$this->getColocalization()) {
            return $allTasksDescr;
        }

        $colocChannels = $this->getColocChannels();
        $colocRuns = $this->getNumberColocRuns();

            /* All the possible coloc runs combining the chosen channels. */
        $runCnt = 0;
        
        for ($i = 0; $i < count($colocChannels) - 1; $i++) {
            for ($j = $i + 1; $j < count($colocChannels); $j++) {

                $chanR = $colocChannels[$i];
                $chanG = $colocChannels[$j];
                
                $allTasksDescr .=
                    $this->getTaskDescrColoc($chanR, $chanG, $runCnt);
                
                if ( $runCnt < $colocRuns ) {
                    $runCnt++;
                } else {
                    error_log("Wrong number of colocalization runs: $runCnt");
                }
            }
        }
        
        return $allTasksDescr;
    }

    /*!
     \brief       Gets options for the '2Dhistogram' task.
     \return      Tcl list with the '2Dhistogram' task and its options.
    */
    private function getImgTaskDescrHistograms( ) {
        
        $allTasksDescr = "";

        /* There should be one 2D histogram per colocalization run. */
        if (!$this->getColocalization()) {
            return $allTasksDescr;
        }

        $colocChannels = $this->getColocChannels();
        $colocRuns = $this->getNumberColocRuns();

            /* All the possible coloc runs combining the chosen channels. */
        $runCnt = 0;
        
        for ($i = 0; $i < count($colocChannels) - 1; $i++) {
            for ($j = $i + 1; $j < count($colocChannels); $j++) {

                $chanR = $colocChannels[$i];
                $chanG = $colocChannels[$j];
                
                $allTasksDescr .=
                    $this->getTaskDescrHistogram($chanR, $chanG, $runCnt);
                
                if ( $runCnt < $colocRuns ) {
                    $runCnt++;
                } else {
                    error_log("Wrong number of colocalization runs: $runCnt");
                }
            }
        }

        return $allTasksDescr;
    }

    
    /*!
     \brief      Gets options for the thumbnail generation task.
     \return     Tcl-compliant list with the thumbnail options.
     \todo       Set thumbtypes in an array at initialization.
    */
    private function getImgTaskDescrThumbnail($thumbType,$thumbID) {

        if (preg_match("/Raw/i",$thumbType)) {
            $this->thumbFrom = "raw";
        } elseif (preg_match("/Dec/i",$thumbType)) {
            $this->thumbFrom = "deconvolved";
        } else {
            $this->thumbFrom = null;
        }

        if (preg_match("/SubImg/i",$thumbType)) {
            $this->thumbSubImg = "subimg";
        } else {
            $this->thumbSubImg = null;
        }

        if (preg_match("/SrcDir/i",$thumbType)) {
            $this->thumbToDir = $this->getSrcDir() . "/hrm_previews";
        } elseif (preg_match("/DstDir/",$thumbType)) {
            $this->thumbToDir = $this->getDestDir() . "/hrm_previews";
        }
        $this->thumbToDir = $this->string2tcllist($this->thumbToDir);

        if (preg_match("/XYXZ/i",$thumbType)) {
            $this->thumbType = "XYXZ";
        } elseif (preg_match("/ortho/i",$thumbType)) {
            $this->thumbType = "orthoSlice";
        } elseif (preg_match("/TimeSFPMovie/i",$thumbType)) {
            $this->thumbType = "timeSFPMovie";
        } elseif (preg_match("/SFP/i",$thumbType)) {
            $this->thumbType = "SFP";
        } elseif (preg_match("/ZMovie/i",$thumbType)) {
            $this->thumbType = "ZMovie";
        } elseif (preg_match("/timeMovie/i",$thumbType)) {
            $this->thumbType = "timeMovie";
        } elseif (preg_match("/ZComparison/i",$thumbType)) {
            $this->thumbType = "compareZStrips";
        } elseif (preg_match("/TComparison/i",$thumbType)) {
            $this->thumbType = "compareTStrips";
        } else {
            $this->thumbType = null;
        }

        $taskDescr = $this->getThumbnailTaskDescr($thumbType,$thumbID);

        return $taskDescr;
    }

    /*!
     \brief       Gets options for the 'image save' task.
     \return      Tcl list with the 'Image save' task and its options.
    */
    private function getImgTaskDescrSave( ) {

        $taskDescr = "";
        
        foreach ($this->imgSaveArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch( $key ) {
            case 'rootName':
                $outName  = $this->getDestImageBaseName();
                $taskDescr .= $this->string2tcllist($outName);
                break;
            case 'listID':
                $taskDescr = $this->string2tcllist($taskDescr);
                $taskDescr = " " . $value . " " . $taskDescr;
                break;
            default:
                error_log("Image save option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /* -------------------------- Setp task ---------------------------------- */

    /*!
      \brief     Gets the STED depletion mode. One channel.
      \param     $channel A channel
      \return    The STED depletion mode.
    */
    private function getStedMode($channel, $parseConfocals = False) {
        $microSetting = $this->microSetting;
        $stedMode = $microSetting->parameter("StedDepletionMode")->value();
        $deplMode = $stedMode[$channel];

        /* In this case the major microscope mode will be confocal, and the
           STED depletion mode will not matter. */
        if ($parseConfocals) {
            if (strstr($deplMode,'confocal')) {
                $deplMode = "vortexPulsed";
            }
        }
        
        return $deplMode;        
    }

    /*!
      \brief     Gets the STED lambda. One channel.
      \param     $channel A channel
      \return    The STED lambda.
    */
    private function getStedLambda($channel) {
        $microSetting = $this->microSetting;
        $stedLambda = $microSetting->parameter("StedWavelength")->value();       
        return $stedLambda[$channel];        
    }

    /*!
      \brief     Gets the STED saturation factor. One channel.
      \param     $channel A channel
      \return    The STED saturation factor.
    */
    private function getStedSaturationFactor($channel) {
        $microSetting = $this->microSetting;
        $stedSatFact =
            $microSetting->parameter("StedSaturationFactor")->value();       
        return $stedSatFact[$channel];        
    }

    /*!
      \brief     Gets the STED immunity factor. One channel.
      \param     $channel A channel
      \return    The STED immunity factor.
    */
    private function getStedImmunity($channel) {
        $microSetting = $this->microSetting;
        $stedImmunity = $microSetting->parameter("StedImmunity")->value();
        return $stedImmunity[$channel];        
    }

    /*!
      \brief     Gets the STED lambda. One channel.
      \param     $channel A channel
      \return    The STED lambda.
    */
    private function getSted3D($channel) {
        $microSetting = $this->microSetting;
        $sted3D = $microSetting->parameter("Sted3D")->value();       
        return $sted3D[$channel];        
    }
    
    /*!
     \brief       Gets the pinhole radius. One channel.
     \param       $channel A channel
     \return      The pinhole radius.
    */
    private function getPinholeRadius($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize")->value();       
        return $pinholeSize[$channel];
    }      

    /*!
     \brief       Gets the microscope type.
     \param       $channel A channel
     \return      The microscope type.
    */
    private function getMicroscopeType($channel) {
        $microChoice = $this->microSetting->parameter('MicroscopeType');
        $micrType = $microChoice->translatedValue();

        if (strstr($micrType,'sted3d')) {
            $micrType = 'sted';
        }
        
        if (strstr($micrType,'sted')) {
            $stedMode = $this->getStedMode($channel);
            
            if (strstr($stedMode,'confocal')) {
                $micrType = 'confocal';
            }
        }
        
        return $micrType;
    }

    /*!
     \brief       Gets the objective's refractive index. Same for all channels.
     \return      The objective's refractive index.
    */
    private function getLensRefractiveIndex( ) {
        $microSetting = $this->microSetting;
        return $microSetting->parameter('ObjectiveType')->translatedValue();
    }

    /*!
     \brief       Gets the numerical aperture. Same for all channels.
     \return      The numerical aperture.
    */
    private function getNumericalAperture( ) {
        $microSetting = $this->microSetting;
        return $microSetting->parameter('NumericalAperture')->value();
    }

    /*!
     \brief       Gets the pinhole spacing. One channel.
     \param       $channel A channel
     \return      The pinhole spacing.
    */
    private function getPinholeSpacing($channel) {
        if ($this->getParameterValue("micr",$channel) != "nipkow") {
            return "";
        }

        $microSetting = $this->microSetting;
        return $microSetting->parameter("PinholeSpacing")->value();
    }

    /*!
     \brief       Gets the Objective Quality. Same for all channels.
     \return      The objective quality.
     \todo        To be implemented in the GUI
    */
    private function getObjectiveQuality( ) {
        return $this->setpArray['objQuality'];
    }

    /*!
     \brief       Gets the excitation photon count. Same for all channels.
     \return      The excitation photon count.
    */
    private function getExcitationPhotonCount( ) {
        if ($this->microSetting->isTwoPhoton()) {
            return 2;
        } else {
            return 1;
        }
    }

    /*!
     \brief       Gets the excitation wavelength. One channel.
     \param       $channel A channel
     \return      The excitation wavelength.
    */
    private function getExcitationWavelength($channel) {
        $microSetting = $this->microSetting;
        $excitationLambdas = $microSetting->parameter("ExcitationWavelength");
        $excitationLambda = $excitationLambdas->value();
        return $excitationLambda[$channel];
    }

    /*!
     \brief      Gets the excitation beam overfill factor. Same for all channels.
     \return     The excitation beam factor.
    */
    private function getExcitationBeamFactor( ) {
        return $this->setpArray['exBeamFill'];
    }

    /*!
     \brief       Gets the emission wavelength. One channel.
     \param       $channel A channel
     \return      The emission wavelength.
    */
    private function getEmissionWavelength($channel) {
        $microSetting = $this->microSetting;
        $emissionLambdas = $microSetting->parameter("EmissionWavelength");
        $emissionLambda = $emissionLambdas->value();
        return $emissionLambda[$channel];
    }

    /*!
     \brief       Gets the imaging direction. Same for all channels.
     \return      Whether the imaging was carried out 'downwards' or 'upwards'.
    */
    private function getImagingDirection( ) {
        $microSetting = $this->microSetting;
        $coverslip = $microSetting->parameter('CoverslipRelativePosition');
        $coverslipPos = $coverslip->value();
        if ($coverslipPos == 'farthest' ) {
            return "downward";
        } else {
            return "upward";
        }
    }

    /*!
     \brief       Gets the medium refractive index. Same for all channels.
     \return      The medium's refractive index.
    */
    private function getMediumRefractiveIndex( ) {
        $microSetting = $this->microSetting;
        $sampleMedium = $microSetting->parameter("SampleMedium");
        return $sampleMedium->translatedValue();
    }

    /*!
     \brief       Gets the sampling distance in the X direction.                 
     \return      The sampling in the X direction, if specified by the user. 
    */
    public function getSamplingSizeX( ) {
        if ($this->microSetting->sampleSizeX() != 0) {
            return $this->microSetting->sampleSizeX();
        } else {
            return "*";
        }
    }

    /*!
     \brief       Gets the sampling distance in the Y direction.                 
     \return      The sampling in the Y direction, if specified by the user.
    */
    public function getSamplingSizeY( ) {
        if ($this->microSetting->sampleSizeY() != 0) {
            return $this->microSetting->sampleSizeY();
        } else {
            return "*";
        }
    }

    /*!
     \brief       Gets the sampling distance in the Z direction.                 
     \return      The sampling in the Z direction, if specified by the user.
    */
    public function getSamplingSizeZ( ) {
        if ($this->microSetting->sampleSizeZ() != 0) {
            return $this->microSetting->sampleSizeZ();
        } else {
            return "*";
        }
    }

    /*!
     \brief       Gets the sampling interval in T.                   
     \return      The sampling in T, if specified by the user.
    */
    public function getSamplingSizeT( ) {
        if ($this->microSetting->sampleSizeT() != 0) {
            return $this->microSetting->sampleSizeT();
        } else {
            return "*";
        }
    }

    /*!
     \brief       Gets the sampling size of the 'raw' image.
     \return      Tcl-list with the sampling sizes.
    */
    private function getSamplingSizes( ) {
        $sampling = $this->getSamplingSizeX();
        $sampling .= " " . $this->getSamplingSizeY();
        $sampling .= " " . $this->getSamplingSizeZ();
        $sampling .= " " . $this->getSamplingSizeT();

        return $this->string2tcllist($sampling);
    }

    /* -------------------------- Algorithm task ----------------------------- */

   /*!
     \brief       Gets options for the 'algorithm' task. One channel.
     \param       $channel A channel
     \return      Tcl list with the deconvolution 'algorithm' task + its options.
    */
    private function getTaskDescrAlgorithm($channel) {

        $taskDescr = "";
        
        foreach ($this->algArray as $key => $value) {

            if ($key != "mode" && $key != "itMode" && $key != 'listID') {
                $taskDescr .= " " . $key . " ";
            }

            switch ( $key ) {
            case 'timeOut':
            case 'pad':
            case 'blMode':
                $taskDescr .= $value;
                break;
            case 'q':
                $taskDescr .= $this->getQualityFactor();
                break;
            case 'brMode':
                $taskDescr .= $this->getBrMode();
                break;
            case 'it':
                $taskDescr .= $this->getIterations();
                break;
            case 'bgMode':
                $taskDescr .= $this->getBgMode();
                break;
            case 'bg':
                $taskDescr .= $this->getBgValue($channel);
                break;
            case 'sn':
                $taskDescr .= $this->getSnrValue($channel);
                break;
            case 'psfMode':
                $taskDescr .= $this->getPsfMode();
                break;
            case 'psfPath':
                $taskDescr .= $this->getPsfPath($channel);
                break;
            case 'mode':
                if ($this->getAlgorithm() == "cmle") {
                    $taskDescr .= " " . $key . " ";
                    $taskDescr .= $value;
                }
                break;
            case 'itMode':
                if ($this->getAlgorithm() == "qmle") {
                    $taskDescr .= " " . $key . " ";
                    $taskDescr .= $value;
                }
                break;
            case 'listID':
                break;
            default:
                error_log("Deconvolution option $key not yet implemented");
            }
        }

        return $this->string2tcllist($taskDescr);
    }
    
    /*!
     \brief       Gets the brick mode.
     \return      Brick mode.
    */
    private function getBrMode( ) {
        $SAcorr = $this->getSAcorr();

        $brMode = "one";

        if ( $SAcorr[ 'AberrationCorrectionNecessary' ] == 1 
             &&  $SAcorr[ 'PerformAberrationCorrection' ] != 0 ) {

            if ($SAcorr[ 'AberrationCorrectionMode' ] == 'automatic' ) {
                $brMode = "auto";
            } else {
                if ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'slice' ) {
                    $brMode = 'sliceBySlice';
                } elseif ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'few' ) {
                    $brMode = 'few';
                }    
            }
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
            return "auto";
        } else if ($bgValue[0] == "object" || $internalValue[0] == "object") {
            return "object";
        } else {
            return "manual";
        }
    }

    /*!
     \brief       Gets the background value. One channel.
     \param       $channel A channel
     \return      The background value.
    */
    private function getBgValue($channel) {
        if ($this->getBgMode() == "auto" || $this->getBgMode() == "object") {
            return 0.0;
        } elseif ($this->getBgMode() == "manual") {
            $deconSetting = $this->deconSetting;
            $bgRate =
                $deconSetting->parameter("BackgroundOffsetPercent")->value();
            return $bgRate[$channel];
        } else {
            error_log("Unknown background mode for channel $channel.");
            return;
        }
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
     \return      PSF mode.
    */
    private function getPsfMode( ) {
        $microSetting = $this->microSetting;
        $psfMode = $microSetting->parameter("PointSpreadFunction")->value();
        if ($psfMode == "theoretical") {
            return "auto";
        } else {
            return "file";
        }
    }

    /*!
     \brief       Gets the PSF path including subfolders created by the user.
     \param       $channel A channel
     \return      Psf path
    */
    private function getPsfPath($channel) {
        if ($this->getPsfMode() == "file") {

            /* The PSF may be located in a different subfolder than the raw data.
             Thus, its path must be found independently of the raw images. */
            $userFileArea = $this->jobDescription->sourceFolder();
            $microSetting = $this->microSetting;
            $psfFiles     = $microSetting->parameter("PSF")->value();
            $psfPath      = trim($userFileArea ."/". $psfFiles[$channel]);
        } else {
            $psfPath = "";
        }

        return $this->string2tcllist($psfPath);
    }

    /*!
     \brief       Gets the deconvolution quality factor.
     \return      The quality factor
    */
    private function getQualityFactor( ) {
        $deconSetting = $this->deconSetting;
        $parameter = $deconSetting->parameter('QualityChangeStoppingCriterion');
        return $parameter->value();
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

    /* -------------- Chromatic Aberration correction tasks ------------------ */
    
    /*!
      \brief    It creates an array with the target channels.
      \return   The target array.
    */
    private function getChansForChromaticCorrection( ) {
        $channelsArray = array();
        
        $chanCnt = $this->getChanCnt();
        if ($chanCnt < 2) {
            return $channelsArray;
        }

        $chromaticParam = $this->deconSetting->parameter("ChromaticAberration");

        for ($chan = 0; $chan < $chanCnt; $chan++) {
            $chromaticChan = $chromaticParam->chanValue( $chan );
            
            foreach ($chromaticChan as $component => $value) {
                if (isset($value) && $value > 0) {
                    array_push($channelsArray, $chan);
                    break;
                }
            }
        }
        
        return $channelsArray;
    }

    
    /* --------------------- Colocalization tasks ---------------------------- */

    /*!
     \brief       Gets options for the 'colocalization' task.
     \param       $chanR A channel number acting as red channel.
     \param       $chanG A channel number acting as green channel.
     \param       $runCnt The number of colocalization tasks
     \return      Tcl list with the 'colocalization' task and its options.
    */
    private function getTaskDescrColoc($chanR, $chanG, $runCnt) {
        
        $taskDescr = "";
        
        foreach ($this->colocArray as $key => $value) {

            if ($key != "listID") {
                if ($key == "threshPercR" || $key == "threshPercG") {
                    if ($this->getColocThreshMode() != "manual") {
                        continue;
                    }
                }
                $taskDescr .= " " . $key . " ";
            }

            switch( $key ) {
            case 'chanR':
                $taskDescr .= $chanR;
                break;
            case 'chanG':
                $taskDescr .= $chanG;
                break;
            case 'threshMode':
                $taskDescr .= $this->getColocThreshMode();
                break;
            case 'threshPercR':
                $taskDescr .= $this->getColocThreshValue($chanR);
                break;
            case 'threshPercG':
                $taskDescr .= $this->getColocThreshValue($chanG);
                break;
            case 'coefficients':
                $coefficients  = $this->getColocCoefficients();
                $taskDescr .= $this->string2tcllist($coefficients);
                break;
            case 'map':
                $taskDescr .= $this->getColocMap();
                break;
            case 'destDir':
                $destDir   = $this->getDestDir() . "/hrm_previews";
                $taskDescr .= $this->string2tcllist($destDir);
                break;
            case 'destFile':
                $destFile  = $this->getThumbBaseName() . ".";
                $destFile .= $this->getColocMap() . ".map_chan";
                $destFile .= $chanR . "_" . "chan" . $chanG;
                $taskDescr .= $destFile;
                break;
            case 'listID':
                $taskDescr  = $this->string2tcllist($taskDescr);
                $taskDescr  = $value . ":" . $runCnt . " " . $taskDescr . " ";
                break;
            default:
                error_log("Colocalization option '$key' not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /*!
     \brief       Gets the value of the boolean choice 'Colocalization Analysis'.
     \return      Whether or not colocalization analysis should be performed.
    */
    private function getColocalization( ) 
    {    
        return $this->analysisSetting->parameter('ColocAnalysis')->value();
    }

    /*!
     \brief       Gets the channel choice for the colocalization analysis.
     \return      Which channels to use in the colocalization analysis.
    */
    private function getColocChannels( ) {
        $colocChannels = $this->analysisSetting->parameter('ColocChannel')->value();
        
            /* Do not count empty elements. Do count channel '0'. */
        return array_filter($colocChannels, 'strlen');
}

    /*!
     \brief       Gets the value of the choice 'Colocalization Coefficients'.
     \return      Which colocalization coefficients should be calculated.
    */
    private function getColocCoefficients( ) 
    {
        $colocCoefficients = "";
        $coefArr = $this->analysisSetting->parameter('ColocCoefficient')->value();

        foreach ($coefArr as $key => $coefficient) {
            $colocCoefficients .= $coefficient . " ";
        }

        return $colocCoefficients;
    }
    
    /*!
     \brief       Gets the value of the choice 'Colocalization Map'.
     \return      Which colocalization map should be created.
    */
    private function getColocMap( ) 
    {    
        return $this->analysisSetting->parameter('ColocMap')->value();
    }

    /*!
     \brief       Gets the colocalization threshold mode.
     \return      The colocalization threshold mode.
    */
    private function getColocThreshMode( ) {
        $bgParam = $this->analysisSetting->parameter("ColocThreshold");
        $bgValue = $bgParam->value();

        if ($bgValue[0] == "auto") {
            return "auto";
        }  else {
            return "manual";
        }
    }

    /*!
     \brief       Gets the coloc background (threshold) value. One channel.
     \param       $channel A channel
     \return      The colocalization background (threshold) value.
    */
    private function getColocThreshValue($channel) {
        if ($this->getColocThreshMode() == "auto") {
            return 0.0;
        } elseif ($this->getColocThreshMode() == "manual") {
            $analysisSetting = $this->analysisSetting;
            $bgRate = $analysisSetting->parameter("ColocThreshold")->value();
            return $bgRate[$channel];
        } else {
            error_log("Unknown colocalization threshold mode.");
            return;
        }
    }

    
     /* --------------------- Histogram tasks ---------------------------- */

     /*!
     \brief       Gets options for the 'histogram' task.
     \param       $chanR A channel number acting as red channel.
     \param       $chanG A channel number acting as green channel.
     \param       $runCnt The number of histogram tasks
     \return      Tcl list with the 'histogram' task and its options.
    */
    private function getTaskDescrHistogram($chanR, $chanG, $runCnt) {

        $taskDescr = "";
        
        foreach ($this->histoArray as $key => $value) {
            
            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }
            
            switch( $key ) {
            case 'chanR':
                $taskDescr .= $chanR;
                break;
            case 'chanG':
                $taskDescr .= $chanG;
                break;
            case 'destDir':
                $destDir  = $this->getDestDir() . "/hrm_previews";
                $taskDescr .= $this->string2tcllist($destDir);
                break;        
            case 'destFile':
                $destFile  = $this->getThumbBaseName() . ".hist_chan" . $chanR;
                $destFile .= "_" . "chan" . $chanG;
                $taskDescr  .= $destFile;
                break;
            case 'listID':
                $taskDescr  = $this->string2tcllist($taskDescr);
                $taskDescr  = $value . ":" . $runCnt . " " . $taskDescr . " ";
                break;
            default:
                error_log("2D histogram option '$key' not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /* ------------------------ Thumbnail tasks------------------------------- */

    /*!
     \brief       Gets options for each of the thumbnail tasks.
     \param       $taskKey  A thumbnail type of task 
     \param       $thumbID  The number of this thumbnail task.
     \return      Tcl-compliant list with the thumbnail generation details.
    */
    private function getThumbnailTaskDescr($taskKey,$thumbID) {

        /* Get the Huygens task name of the thumbnail task */
        $task = $this->getTaskName($taskKey,$thumbID);
        if ($task == "") {
            return;
        }        
        
        $taskDescr = "";

        foreach ($this->thumbArray as $key => $value) {

            if ($key == "image" && !isset($this->thumbFrom)) {
                $taskDescr .= " ";
            } else {
                $taskDescr .= " " . $key . " ";
            }

            /* Notice that the 'size' is added to all thumbnail tasks even
             though some of them don't need it. */
            switch( $key ) {
            case 'image':
                $taskDescr .= $this->thumbFrom;
                break;
            case 'destDir':
                $taskDescr .= $this->thumbToDir;
                break;
            case 'destFile':
                $taskDescr .= $this->getThumbnailName();
                break;
            case 'type':
                $taskDescr .= $this->thumbType;
                break;
            case 'size':
                $taskDescr .= $value;
                break;
            default:
                error_log("Thumb preview option $key not yet implemented");
            }
        }

        $taskDescr = $this->string2tcllist($taskDescr);
        $taskDescr = " " . $task . " " . $taskDescr;

        return $taskDescr;
    }

    /*!
     \brief      Gets the destination file name of the thumbnail.
     \return     A name for the thumbnail file.
    */
    private function getThumbnailName( ) {

        $destFile = $this->getThumbBaseName();

        if ($this->thumbFrom == "raw") {
            switch( $this->thumbType ) {
            case 'ZMovie':
            case 'timeMovie':
            case 'timeSFPMovie':
            case 'compareZStrips':
            case 'compareTStrips':
                break;
            case 'XYXZ':
                $destFile = basename($this->srcImage);
                $destFile .= $this->getSubImageSuffix($this->thumbSubImg);
                break;
            case 'orthoSlice':
                $suffix = ".original";
                break;
            case 'SFP':
                $suffix = ".original.sfp";
                break;
            default: 
                error_log("Unknown thumbnail type");               
            }
        }

        if ($this->thumbFrom == "deconvolved") {
            switch( $this->thumbType ) {
            case 'XYXZ':
            case 'orthoSlice':
            case 'compareZStrips':
            case 'compareTStrips':
                break;
            case 'SFP':
                $suffix = ".sfp";
                break;
            case 'ZMovie':
                $suffix = ".stack";
                break;
            case 'timeMovie':
                $suffix = ".tSeries";
                break;
            case 'timeSFPMovie':
                $suffix = ".tSeries.sfp";
                break;
            default:
                error_log("Unknown thumbnail type");                
            }
        }
        
        if (isset($suffix)) {
            $destFile .= $suffix;
        }
        $destFile = $this->string2tcllist($destFile);

        return $destFile;
    }

    /*!
     \brief       Gets the subimage name between parenthesis as suffix
     \param       $subImg Whether or not a sub image is being dealt with
     \return      The image suffix
    */
    private function getSubImageSuffix($subImg) {
        if (isset($this->subImage) && $subImg != null) {
            $suffix = " (";
            $suffix .= $this->tcllist2string($this->subImage);
            $suffix .= ")";                    
        } else {
            $suffix = "";
        }
        return $suffix;
    } 

    /* ----------------------------- Utilities ------------------------------- */

    /*!
     \brief       Gets the environment export format option
     \return      Tcl-complaint nested list with the export format details
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
     \brief       Gets the basic name that all thumbnails share, based on job id.
     \return      The thumbnail basic name.
     */
    private function getThumbBaseName( ) {
        $basename = basename($this->destImage);
        $filePattern = "/(.*)_(.*)_(.*)\.(.*)$/";
        preg_match($filePattern,$basename,$matches);
        return $matches[2] . "_" . $matches[3] . "." . $matches[4];
    }

    /*!
     \brief       Gets the parameter confidence level for all channels.
     \param       $paramName The parameter name.
     \return      The confidence level. 
    */
    private function getParameterConfidence($paramName) {

        switch ( $paramName ) {
        case 'pr':
        case 'micr':
        case 'imagingDir':
        case 'ps':
        case 'objQuality':
        case 'pcnt':
        case 'ex':
        case 'em':
        case 'exBeamFill':
        case 'ri':
        case 'ril':
        case 'na':
        case 'stedMode':
        case 'stedLambda':
        case 'stedSatFact':
        case 'stedImmunity':
        case 'sted3D':
            $chanCnt = $this->getChanCnt();
            $cList = "";

            for($chan = 0; $chan < $chanCnt; $chan++) {
                $cLevel = $this->getConfidenceLevel($paramName,$chan);
                $cList .= "  " . $cLevel;
            }
            $paramConf = $this->string2tcllist($cList);
            break;
        case 's':
            $cList  = $this->getConfidenceLevel("sX",0) . " ";
            $cList .= $this->getConfidenceLevel("sX",0) . " ";
            $cList .= $this->getConfidenceLevel("sZ",0) . " ";
            $cList .= $this->getConfidenceLevel("sT",0);
            $paramConf = $this->string2tcllist($cList);
            break;
        case 'iFacePrim':
        case 'iFaceScnd':
            $paramConf = $this->getConfidenceLevel($paramName,0);
            break;
        default:
            error_log("Parameter $paramName not yet implemented");
        }

        return $paramConf;
    }

    /*!
     \brief      Gets the confidence level of one parameter and one channel.
     \param      $parameter The parameter name
     \param      $channel   The channel number
     \return     'noMetaData' is the user entered a value for the parameter,
     \return     'default' otherwise.
    */
    private function getConfidenceLevel($parameter,$channel) {

        /* Parameter not set by the user, should be read from the metadata. */
        if ($this->getParameterValue($parameter,$channel) == "*") {
            return "default";
        }
 
        /* Parameters initialized with a value in setpArray do not exist 
         in HRM yet. We should try to read them from the metadata. */
        if (array_key_exists($parameter, $this->setpArray)) {
            if ($this->setpArray[$parameter] != '' ) {
                return "default";
            } 
        }             

        /* Parameter set by the user. */
        return "noMetaData";
    }

    /*!
     \brief       Gets the value and confidence level of specific job parameters.
     \param       $paramName The name of the parameter.
     \param       $default   A default value for the parameter.
     \return      A Tcl-compliant list with values and confidence levels.
    */
    private function getParameter($paramName,$default=null) {

        /* Start by adding the parameter name */
        $paramList = " " . $paramName . " ";

        /* Then add the parameter value */
        switch ( $paramName ) {
        case 's':
            $paramList .= $this->getSamplingSizes();
            break;
        case 'iFacePrim':
        case 'iFaceScnd':
            $paramList .= $default;
            break;
        case 'micr':
        case 'pr':
        case 'imagingDir':
        case 'ps':
        case 'objQuality':
        case 'pcnt':
        case 'ex':
        case 'em':
        case 'exBeamFill':
        case 'ri':
        case 'ril':
        case 'na':
        case 'stedMode':
        case 'stedLambda':
        case 'stedSatFact':
        case 'stedImmunity':
        case 'sted3D':
            $chanCnt = $this->getChanCnt();
            $param = "";
            for($chan = 0; $chan < $chanCnt; $chan++) {
                if (!$default) {
                    $param .= $this->getParameterValue($paramName,$chan);
                    $param .= " ";
                } else {
                    $param .= $default . " ";
                }
            }
            $paramList .= $this->string2tcllist($param);
            break;
        default:
            error_log("Multichannel parameter $paramName not yet implemented");
        }

        /* Then add the parameter confidence level */
        $paramList .= " " . $this->setpConfArray[$paramName] . " ";
        $paramList .= $this->getParameterConfidence($paramName);

        return $paramList;
    }

    /*!
     \brief      Gets the value of specific parameters.
     \param      $paramName  The parameter name
     \param      $channel    A channel number
     \return     The parameter value when existing, '*' otherwise.
    */
    private function getParameterValue($paramName,$channel) {
        switch ( $paramName ) {
        case 'micr':
            $parameterValue = $this->getMicroscopeType($channel);
            break;
        case 'pr':
            $parameterValue = $this->getPinholeRadius($channel);
            break;
        case 'imagingDir':
            $parameterValue = $this->getImagingDirection();
            break;
        case 'ps':
            $parameterValue = $this->getPinholeSpacing($channel);
            break;
        case 'objQuality':
            $parameterValue = $this->getObjectiveQuality();
            break;
        case 'pcnt':
            $parameterValue = $this->getExcitationPhotonCount();
            break;
        case 'ex':
            $parameterValue = $this->getExcitationWavelength($channel);
            break;
        case 'em':
            $parameterValue = $this->getEmissionWavelength($channel);
            break;
        case 'exBeamFill':
            $parameterValue = $this->getExcitationBeamFactor();
            break;
        case 'ri':
            $parameterValue = $this->getMediumRefractiveIndex();
            break;
        case 'ril':
            $parameterValue = $this->getLensRefractiveIndex();
            break;
        case 'na':
            $parameterValue = $this->getNumericalAperture();
            break;
        case 'sX':
            $parameterValue = $this->getSamplingSizeX();
            break;
        case 'sZ':
            $parameterValue = $this->getSamplingSizeZ();
            break;
        case 'sT':
            $parameterValue = $this->getSamplingSizeT();
            break;
        case 'stedMode':
            $parameterValue = $this->getStedMode($channel, True);
            break;
        case 'stedLambda':
            $parameterValue = $this->getStedLambda($channel);
            break;
        case 'stedSatFact':
            $parameterValue = $this->getStedSaturationFactor($channel);
            break;            
        case 'stedImmunity':
            $parameterValue = $this->getStedImmunity($channel);
            break;
        case 'sted3D':
            $parameterValue = $this->getSted3D($channel);
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
     \brief       Gets the Huygens task name of a task.
     \brief       Notice that integers often need to be appended to the names.
     \param       $key   A task array key
     \param       $task  A task compliant with the Huygens template task names
     \return      The task name (includes channel number, preview number, etc.)
    */
    private function getTaskName($key,$task) {
        switch ($task) {
        case 'imgOpen':
        case 'setp':
        case 'autocrop':
        case 'adjbl':
        case 'imgSave':
        case 'stabilize':
            break;
        case 'coloc':
        case 'hist':
            $task = $this->getNameTaskMultiChan($task);
            break;
        case 'previewGen':
            $task = $this->getNameTaskPreviewGen($key,$task);
            break;
        case 'shift':
            $task = $this->getNameTaskChromatic($task);
            break;
        case '':
            if ($key == 'algorithms') {
                $task = $this->getNameTaskAlgorithm();
            }
            break;
        default:
            error_log("Huygens template task '$task' not yet implemented.");
        }
        
        return $task;            
    }

    /*!
      \brief
      \return
    */
    private function getNameTaskChromatic($task) {
        
        $chromaticTasks = "";

        $channelsArray = $this->getChansForChromaticCorrection();

        if (empty($channelsArray)) {
            return $chromaticTasks;
        }

        foreach ($channelsArray as $chanKey => $chan) {
            $chromaticTasks .= $task.":$chanKey ";
        }
        
        return trim($chromaticTasks);
    }

    /*!
     \brief       Gets the Huygens deconvolution task names of every channel
     \return      The Huygens deconvolution task names
    */
    private function getNameTaskAlgorithm( ) {
        $chanCnt = $this->getChanCnt();
        $algorithms = "";
        for($chan = 0; $chan < $chanCnt; $chan++) {
            $algorithms .= $this->getAlgorithm().":$chan ";
        }
        return trim($algorithms);
    }

    /*!
     \brief       Gets the Huygens task name of a task run every two channels.
     \param       $key A task array key
     \return      The Huygens task name.
     */
    private function getNameTaskMultiChan($task) {
        
        $tasks = "";

        /* At the moment there are only coloc/hist tasks as multichannel task.
         There must be as many runs as specified by the coloc channels. */
        $runCnt = $this->getNumberColocRuns();

        if ($runCnt == 0) return $tasks;
        
        /* The template task run counter starts at 0. */
        for ($run = 0; $run < $runCnt; $run++ ) {
            $tasks .= $task . ":$run ";
        }
        
        return trim($tasks);
    }
    
    /*!
     \brief       Gets the Huygens task name of a thumbnail task
     \param       $key   A task array key
     \param       $task  A task compliant with the Huygens template task names
     \return      The Huygens preview task name
    */
    private function getNameTaskPreviewGen($key,$task) {
        global $useThumbnails;
        global $saveSfpPreviews;
        global $movieMaxSize;

        if (!$useThumbnails) {
            return;
        }
 
        if (strstr($key, 'SFP') && !$saveSfpPreviews) {
            $task = "";
        } elseif (strstr($key, 'SubImg') && !$this->hasSubImage()) {
            $task = "";
        } elseif (strstr($key, 'ZComparison') && !$this->compareZviews) {
            $task = "";
        } elseif (strstr($key, 'TComparison') && !$this->compareTviews) {
            $task = "";
        } elseif (strstr($key, 'Movie') && $movieMaxSize == 0) {
            $task = "";
        } else {
            $task .= ":" . $this->thumbCnt;
            $this->thumbCnt++;
        }

        return $task;
    }

    /*!
     \brief   Gets the number of colocalization runs as a result of combining
              the choice of channels for colocalization analysis.
     \return  The number of colocalization runs.
    */
    private function getNumberColocRuns ( ) {

        $colocRuns = 0;

        if ($this->getColocalization() == "1") {
            
            $chanCnt = count($this->getColocChannels());

                /* The number of combinations between channels obeys the following
                 formula: n ( n - 1 ) / 2 ; where n is the number of channels. */
            $colocRuns = $chanCnt * ( $chanCnt - 1) / 2;
        }

        return $colocRuns;
    }

    /*!
     \brief       Checks whether a JPEG can be made from the image.
     \param       $image The image to be checked.
    */
    private function isEligibleForSlicers($image) {
        global $maxComparisonSize;

        $this->compareZviews = TRUE;
        $this->compareTviews = TRUE;

        if ($maxComparisonSize == 0) {
            $this->compareZviews = FALSE;
            $this->compareTviews = FALSE;
            return;
        }
        
        $imgDims = $this->getImageDimensions($image);

        $imgSizeX = $imgDims['sizeX'];
        $imgSizeY = $imgDims['sizeY'];
        $imgSizeZ = $imgDims['sizeZ'];
        $imgSizeT = $imgDims['sizeT'];

        if ($imgSizeX == 0 && $imgSizeY == 0) {
            $this->compareZviews = FALSE;
            $this->compareTviews = FALSE;
            return;
        }

        if ($imgSizeX < $maxComparisonSize) {
            $imgSizeX = $maxComparisonSize;
        }

        if ($imgSizeY < $maxComparisonSize) {
            $imgSizeY = $maxComparisonSize;
        }

        /* It could happen that even if imgSizeX and imgSizeY are small the image
         contains so many slices or time frames that the slicer gets huge. */
        $slicerPixelsX  = 2 * $imgSizeX;
        $slicerPixelsYZ = $imgSizeY * $imgSizeZ;
        $slicerPixelsYT = $imgSizeY * $imgSizeT;

            /* The maximum number of pixels per dimension that the JPEG libraries 
         can handle. If the image is larger than this, we won't be able to
         generate a slicer preview. */
        $maxPixelsPerDim = 65000;

        if ($slicerPixelsX >= $maxPixelsPerDim) {
            $this->compareZviews = FALSE;
            $this->compareTviews = FALSE;            
            return;
        }

        if ($slicerPixelsYZ >= $maxPixelsPerDim) {
            $this->compareZviews = FALSE;
        }
            
        if ($slicerPixelsYT >= $maxPixelsPerDim) {
            $this->compareTviews = FALSE;
        }
    }

    /*
     \brief       Gets the open image series mode for time series.
     \return      The series mode: whether auto or off.
    */
    private function getSeriesMode( ) {

        if ($this->jobDescription->autoseries()) {
            $seriesMode = "auto";
        } else {
            $seriesMode = "off";
        }
        
        return $seriesMode;
    }

    /*!
     \brief       Whether or not an image has sub images. 
     \return      Boolean 
    */
    private function hasSubImage( ) {
        if (isset($this->subImage)) {
            return true;
        } else {
            return false;
        }
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
     \brief       Gets the number of channels selected by the user.
     \return      Number of channels.
    */
    private function getChanCnt( ) {
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
     \brief       Sets the name of the source image with subimages, if any.
    */
    private function setSrcImage( ) {
        $this->srcImage = $this->jobDescription->sourceImageName();

        /*If a (string) comes after the file name, the string is interpreted
         as a subimage. Currently this is for LIF and CZI files only. */
        if ( preg_match("/^(.*\.(lif|czi))\s\((.*)\)/i",
                        $this->srcImage, $match) ) {
            $this->srcImage = $match[1];
            $this->subImage = $match[3];
        }
    }

    /*!
     \brief       Gets the directory of the source image.
     \return      A file path.
    */
    private function getSrcDir( ) {
        return dirname($this->srcImage);
    }

    /*!
     \brief       Sets the name of the destination image.
    */
    private function setDestImage ( ) {
        $this->destImage = $this->jobDescription->destinationImageFullName();
        $fileType = $this->getOutputFileExtension();
        $this->destImage = $this->destImage.".".$fileType;
    }

    /*!
     \brief      Gets a basic name for the deconvolved image.
     \return     The image's basic name.
    */
    private function getDestImageBaseName( ) {
        $destInfo = pathinfo($this->destImage);
        return basename($this->destImage,'.'.$destInfo['extension']);
    }

    /*!
     \brief      Gets x, y, z, t and channel dimensions.
     \return     An array with X,Y,Z,T,C dimensions.
    */
    private function getImageDimensions($image) {

        /* Get file path, name and time series option */
        $pathInfo = pathinfo($image);
        $path = $pathInfo['dirname'];
        $filename = $pathInfo['basename'];
        $series = $this->getSeriesMode();
        $opt = "-path \"$path\" -filename \"$filename\" -series $series";
        
        /* Retrieve the image dimensions */
        return askHuCore( "reportImageDimensions", $opt );
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
    private function getOutputFileExtension( ) {
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->extension();
    }

    /* ----------------------------------------------------------------------- */
}


?>