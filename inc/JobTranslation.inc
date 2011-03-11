<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

  /*!
   \class  JobTranslation
   \brief  Converts deconvolution parameters into a Huygens batch template.

   This class builds Tcl-compliant nested lists which summarize the tasks and 
   properties of the deconvolution job. Tasks describing the thumbnail products
   of an HRM deconvolution job are also included. The resulting structure is 
   a Huygens batch template formed by nested lists.

   Template structure:

   - 1 Job:
       - Job info.
       - Job main tasks list:
           - Set environment
           - Set task ID
       - Set environment details: 
           - resultDir
           - perJobThreadCnt
           - concurrentJobCnt
           - exportFormat
           - timeOut
       - Set taskID details:
           - Set taskID info:
               - state
               - tag
               - timeStartAbs
               - timeOut
           - Set taskList per taskID:
               - imgOpen
               - setp
               - Deconvolution algorithm: one per channel
               - zDrift (optionally)
               - previewGen (one per thumbnail type)
               - imgSave
           - Set details per task (imgOpen, setp,..)           
  */

Class JobTranslation {

    /*!
     \var     $template
     \brief   Batch template summarizing the deconvolution job and thumbnail tasks.
    */
    public $template;

    /*!
     \var     $jobInfo
     \brief   A Tcl list with job information for the template header.
     */
    private $jobInfo;

    /*!
     \var     $jobMainTasks
     \brief   A Tcl list with the names of the main job tasks
    */
    private $jobMainTasks;

    /*!
      \var    $envDetails
      \brief  A Tcl list with extra data: number of cores, timeout, etc. 
    */
    private $envDetails;


    /*!
      \var    $taskIDDetails
      \brief  A Tcl list with microscopic, restoration and thumbnail data
    */
    private $taskIDDetails;

    /*!
      \var    $envArray
      \brief  Array with extra data: number of cores, timeout, etc.
    */
    private $envArray;

    /*!
      \var    $taskIDArray
      \brief  Array with restoration and thumbnail operations.
    */
    private $taskIDArray;

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
     \var     $previewCnt
     \brief   An integer that keeps track of the number of preview tasks
    */
    private $previewCnt;

    /* ----------------------------------------------------------------------- */

    /*!
     \brief       Constructor
     \param       $jobDescription JobDescription object
    */
    public function __construct($jobDescription) {
        $this->initialize($jobDescription);
        $this->setJobInfo();
        $this->setJobMainTasks();
        $this->setEnvironmentDetails();
        $this->setTaskIDDetails();
        $this->assembleTemplate();
    }

    /*!
     \brief       Sets class general properties to initial values
    */
    private function initialize($jobDescription) {
        $this->jobDescription = $jobDescription;
        $this->microSetting = $jobDescription->parameterSetting;
        $this->deconSetting = $jobDescription->taskSetting;
        $this->initializePreviewCounter();
        $this->setEnvironmentArray();
        $this->setTaskIDArray();
        $this->setSrcImage();
        $this->setDestImage();
        $this->setHuImageFormat();
    }

    /*!
     \brief       Sets env array with extra data: number of cores, timeout, etc.
    */
    private function setEnvironmentArray( ) {
        $this->envArray = array ( 'resultDir'         =>  '',
                                  'perJobThreadCnt'   =>  'auto',
                                  'concurrentJobCnt'  =>  '1',
                                  'OMP_DYNAMIC'       =>  '1',
                                  'timeOut'           =>  '10000',
                                  'exportFormat'      =>  '' );
    }

    /*!
     \brief       Sets tasks array with microscopic, restoration and preview data
     \todo        Add a field 'zdrift' when it is implemented in the GUI.
    */
    private function setTaskIDArray( ) {

        $this->taskIDArray = array ( 'open'                     =>  'imgOpen',
                                     'setParameters'            =>  'setp',
                                     'adjustBaseline'           =>  'adjbl',
                                     'algorithms'               =>  '',
                                     'XYXZRawAtSrcDir'          =>  'previewGen',
                                     'XYXZRawLifAtSrcDir'       =>  'previewGen',
                                     'XYXZRawAtDstDir'          =>  'previewGen',
                                     'XYXZDecAtDstDir'          =>  'previewGen',
                                     'orthoRawAtDstDir'         =>  'previewGen',
                                     'orthoDecAtDstDir'         =>  'previewGen',
                                     'ZMovieDecAtDstDir'        =>  'previewGen',
                                     'TimeMovieDecAtDstDir'     =>  'previewGen',
                                     'TimeSFPMovieDecAtDstDir'  =>  'previewGen',
                                     'SFPRawAtDstDir'           =>  'previewGen',
                                     'SFPDecAtDstDir'           =>  'previewGen',
                                     'ZComparisonAtDstDir'      =>  'previewGen',
                                     'TComparisonAtDstDir'      =>  'previewGen',
                                     'save'                     =>  'imgSave');
    }

    /*!
     \brief       Sets the info header of the batch template.
    */
    private function setJobInfo( ) {
        $this->jobInfo = "title ";
        $this->jobInfo .= $this->getTemplateTitle();
        $this->jobInfo .= " version ";
        $this->jobInfo .= $this->getTemplateVersion();
        $this->jobInfo .= " templateName ";
        $this->jobInfo .= $this->getTemplateName();
        $this->jobInfo .= " date ";
        $this->jobInfo .= $this->getTemplateDate();
        $this->jobInfo = $this->string2tcllist($this->jobInfo);
        $this->jobInfo = "info " . $this->jobInfo;
    }

    /*!
     \brief       Sorts and sets the main job tasks: setEnv and taskIDs.
    */
    private function setJobMainTasks() {
        $this->jobMainTasks = "setEnv ";
        $this->jobMainTasks .= $this->getTaskID();
        $this->jobMainTasks = $this->string2tcllist($this->jobMainTasks);
        $this->jobMainTasks = "taskList " . $this->jobMainTasks;
    }

    /*!
     \brief       Sets a Tcl list with extra data: number of cores, timeout, etc. 
    */
    private function setEnvironmentDetails( ) {
        $this->envDetails = "setEnv "; 
        $this->envDetails .= $this->buildEnvironmentList();
    }
    
    /*!
     \brief       Sets a Tcl list with microscopic and restoration data. 
    */
    private function setTaskIDDetails( ) {
        $this->taskIDDetails = $this->getTaskID();
        $this->taskIDDetails .= " " . $this->buildTaskList();
    }

    /*!
     \brief       Puts the Huygens Batch template together
    */
    private function assembleTemplate( ) {
        $this->template = $this->jobInfo . "\n" . $this->jobMainTasks . "\n";
        $this->template .= $this->envDetails . "\n " . $this->taskIDDetails;
    }


    /* ------------------------ Main lists handlers ---------------------------- */


    /*!
     \brief       Builds a Tcl list with extra data: number of cores, etc. 
     \return      The Tcl-compliant nested list
    */
    private function buildEnvironmentList( ) {
        $environmentList = "";
        foreach ($this->envArray as $envOption => $envValue) {
            if ($envOption == "resultDir") {
                $envValue = $this->getDestDir();
            } elseif ($envOption == "exportFormat") {
                $envValue = $this->getExportFormat();
            }
            $environmentList .= " " . $envOption;
            $environmentList .= " " . $envValue;
        }
        return $this->string2tcllist($environmentList);
    }

    /*!
     \brief       Get the job export format feature as a list of options
     \return      Tcl-complaint nested list with the export format options
    */
    private function getExportFormat( ) {
        $outputType = $this->getOutputFileType();
        if (preg_match("/tif/",$outputType)) {
            $multidir = 1;
        } else {
            $multidir = 0;
        }
        $exportFormat = "type ";
        $exportFormat .= $outputType;
        $exportFormat .= " multidir ";
        $exportFormat .= $multidir;
        $exportFormat .= " cmode scale";

        return $this->string2tcllist($exportFormat);
    }

    /*!
     \brief       Builds a Tcl list with microscopic, restoration and preview data. 
     \return      The Tcl-compliant nested list
    */
    private function buildTaskList( ) {
        $taskList = $this->getTaskIDInfo();
        $taskList .= $this->getTaskIDDeconTasks();
        $taskList .= $this->getTaskIDDetails();
        return $this->string2tcllist($taskList);
    }

    /*!
     \brief       Gets a unique taskID for the Huygens Scheduler
     \return      The taskID
    */
    private function getTaskID( ) {
        /* As long as the Huygens Scheduler receives only one job at a time
         the taskID will be unique and 0. */
        $taskID = "taskID:0";
        return $taskID;
    }

    /*!
     \brief       Gets scheduling information oo the tasks.
     \return      The Tcl-compliant nested list with task information
    */
    private function getTaskIDInfo( ) {

        /* The job is sent to the Huygens Scheduler to start right away.
         Therefore the state is ready and the timeStartAbs is just now. */
        $taskInfo = "state ";
        $taskInfo .= $this->getTaskState();
        $taskInfo .= " tag " ;
        $taskInfo .= $this->getTaskTag();
        $taskInfo .= " timeStartAbs ";
        $taskInfo .= $this->getAbsTime();
        $taskInfo .= " timeOut ";
        $taskInfo .= $this->getTaskIDTimeOut();
        $taskInfo = $this->string2tcllist($taskInfo);
        $taskInfo = "info " . $taskInfo;
            
        return $taskInfo;
    }

    /*!
     \brief       Gets the taskID timeout
     \return      The timeout in seconds
    */
    private function getTaskIDTimeOut( ) {
        $timeOut = 10000;
        return $timeOut;
    }

    /*!
     \brief       Gets the task state.
     \return      The task state
    */
    private function getTaskState( ) {
        /* As long as the Huygens Scheduler receives only one job at a time
         the task will not be queued when it arrives at the Scheduler but
         executed immediatly, thus its state will be 'readyToRun', invariably */
        $taskState = "readyToRun";
        return $taskState;
    }

    /*!
     \brief       Gets the time lapse since 1st January 1970, in seconds.
     \return      The seconds since Epoch time until now.
    */
    private function getAbsTime( ) {
        $absTime = time();
        return $absTime;
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

    /*!
     \brief       Gets the Huygens subtask names of a deconvolution. 
     \return      The Tcl-compliant nested list with subtask names
    */
    private function getTaskIDDeconTasks( ) {
        $taskList = "";
        foreach ($this->taskIDArray as $key => $task) {
            $task = $this->parseTask($key,$task);
            if ($task != "") {
                $taskList .= $task ." ";
            }
        }
        $taskList = $this->string2tcllist($taskList);
        $taskList = " taskList " . $taskList;
        return $taskList;
    }

    /*!
     \brief       Gets specific details of each deconvolution task. 
     \return      The Tcl-compliant nested list with task details
    */
    private function getTaskIDDetails( ) {

        $this->initializePreviewCounter();

        $taskList = "";
        foreach ($this->taskIDArray as $key => $task) { 
            switch ( $key ) {
            case 'open':
                $taskList .= $this->getTaskIDImgOpen($task);
                break;
            case 'save':
                $taskList .= $this->getTaskIDImgSave($task);
                break;
            case 'setParameters':
                $taskList .= $this->getTaskIDSetp($task);
                break;
            case 'adjustBaseline':
                $taskList .= $this->getTaskIDAdjbl($task);
                break;
            case 'algorithms':
                $taskList .= $this->getTaskIDAlgorithms();
                break;
            case 'zdrift':
                $taskList .= $this->getTaskIDZDrift($task);
                break;
            case 'XYXZRawAtSrcDir':
                $taskList .= $this->getTaskIDXYXZ($key,$task,"raw","src");
                break;
            case 'XYXZRawLifAtSrcDir':
                $taskList .= $this->getTaskIDXYXZ($key,$task,"raw","src","lif");
                break;
            case 'XYXZRawAtDstDir':
                $taskList .= $this->getTaskIDXYXZ($key,$task,"raw","dest");
                break;
            case 'XYXZDecAtDstDir':
                $taskList .= $this->getTaskIDXYXZ($key,$task,"dec","dest");
                break;
            case 'orthoRawAtDstDir':
                $taskList .= $this->getTaskIDOrtho($key,$task,"raw","dest");
                break;
            case 'orthoDecAtDstDir':
                $taskList .= $this->getTaskIDOrtho($key,$task,"dec","dest");
                break;
            case 'SFPRawAtDstDir':
                $taskList .= $this->getTaskIDSFP($key,$task,"raw","dest");
                break;
            case 'SFPDecAtDstDir':
                $taskList .= $this->getTaskIDSFP($key,$task,"dec","dest");
                break;
            case 'ZMovieDecAtDstDir':
                $taskList .= 
                    $this->getTaskIDMovie($key,$task,"dec","dest","ZMovie");
                break;
            case 'TimeSFPMovieDecAtDstDir':
                $taskList .= 
                    $this->getTaskIDMovie($key,$task,"dec","dest","timeSFPMovie");
                break;
            case 'TimeMovieDecAtDstDir':
                $taskList .= 
                    $this->getTaskIDMovie($key,$task,"dec","dest","timeMovie");
                break;
            case 'ZComparisonAtDstDir':
                $taskList .= $this->getTaskIDZComparison($key,$task,"dest");
                break;
            case 'TComparisonAtDstDir':
                $taskList .= $this->getTaskIDTComparison($key,$task,"dest");
                break;
            default:
                $taskList = "";
            }
        }
        return $taskList;
    }

    /*!
     \brief       Get the Huygens task name of a task.
     \param       $key A task array key
     \param       $task A task compliant with the Huygens template task names
     \return      The Huygens task name
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

        if (!$useThumbnails) {
            return;
        } else {
            if (strstr($key, 'SFP')) {
                if ($saveSfpPreviews) {
                    $task = $task . ":" . $this->previewCnt;
                    $this->previewCnt++;
                } else {
                    $task = "";
                }
            } elseif (strstr($key, 'Lif')) {
                if ($this->isImageLif()) {
                    $task = $task . ":" . $this->previewCnt;
                    $this->previewCnt++;
                } else {
                    $task = "";
                }
            } else {
                $task = $task . ":" . $this->previewCnt;
                $this->previewCnt++;
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

    /* ------------------------------- Tasks ----------------------------------- */
    
    /*!
     \brief       Gets options for the 'image open' task
     \param       $task A task from the taskID array that should be 'imgOpen'.
     \return      Tcl list with the'Image open' task and its options
    */
    private function getTaskIDImgOpen($task) {

        $imgOpen =  "path ";
        $imgOpen .= $this->string2tcllist($this->srcImage);
        if (isset($this->subImage)) {
            $imgOpen .= " subImg ";
            $imgOpen .= $this->subImage;
        }
        $imgOpen .= " series auto index 0";
        $imgOpen = $this->string2tcllist($imgOpen);
        $imgOpen = " " . $task . " " . $imgOpen;
        return $imgOpen;
    }

    /*!
     \brief       Gets options for the 'set parameters' task
     \param       $task A task from the taskID array that should be 'setp'.
     \return      Tcl list with the 'Set parameters' task and its options
    */
    private function getTaskIDSetp($task) {

        $options = "completeChanCnt ";
        $options .= $this->getNumberOfChannels();
        $options .= " iFacePrim ";
        $options .= $this->getiFacePrim();
        $options .= " prState,iFacePrim ";
        $options .= $this->getiFacePrimConfidence();
        $options .= " iFaceScnd ";
        $options .= $this->getiFaceScnd();
        $options .= " prState,iFaceScnd ";
        $options .= $this->getiFaceScndConfidence();
        $options .= " pr ";
        $options .= $this->getPinholeRadius();
        $options .= " parState,pr ";
        $options .= $this->getPinholeRadiusConfidenceList();
        $options .= " parState,imagingDir ";
        $options .= $this->getImagingDirVerification();
        $options .= " imagingDir ";
        $options .= $this->getImagingDirection();
        $options .= " parState,ps ";
        $options .= $this->getPinSpacingVerification();
        $options .= " ps ";
        $options .= $this->getPinholeSpacing();
        $options .= " s ";
        $options .= $this->getSamplingSizes();
        $options .= " parState,s ";
        $options .= $this->getSamplingVerification();
        $options .= " parState,objQuality ";
        $options .= $this->getObjQualityVerification();
        $options .= " objQuality ";
        $options .= $this->getObjQuality();
        $options .= " parState,pcnt ";
        $options .= $this->getExcitationPcntVerification();
        $options .= " pcnt ";
        $options .= $this->getExcitationPcnt();
        $options .= " ex ";
        $options .= $this->getExcitationLambda();
        $options .= " parState,ex ";
        $options .= $this->getExcitationLVerification();
        $options .= " parState,exBeamFill ";
        $options .= $this->getExBeamVerification();
        $options .= " exBeamFill ";
        $options .= $this->getExBeamFill();
        $options .= " parState,em ";
        $options .= $this->getEmissionLVerification();
        $options .= " em ";
        $options .= $this->getEmissionLambda();
        $options .= " parState,ri ";
        $options .= $this->getMediumRIndexVerification();
        $options .= " ri ";
        $options .= $this->getMediumRefractiveIndex();
        $options .= " micr ";
        $options .= $this->getMicroscopeType();
        $options .= " parState,micr ";
        $options .= $this->getMicroTypeConfidenceList();
        $options .= " parState,ril ";
        $options .= $this->getLensRIndexConfidenceList();
        $options .= " ril ";
        $options .= $this->getLensRefractiveIndex();
        $options .= " parState,na ";
        $options .= $this->getNumericalAVerification();
        $options .= " na ";
        $options .= $this->getNumericalAperture();
        $options = $this->string2tcllist($options);
        $setp = " setp " . $options;

        return $setp;
    }

    /*!
     \brief       Gets options for the 'image save' task
     \param       $task A task from the taskID array that should be 'imgSave'.
     \return      Tcl list with the 'Image save' task and its options
    */
    private function getTaskIDImgSave($task) {
        $destInfo = pathinfo($this->destImage);
        $destImage =  basename($this->destImage,'.'.$destInfo['extension']);

        $imgSave = "rootName ";
        $imgSave .= $this->string2tcllist($destImage);
        $imgSave = $this->string2tcllist($imgSave);
        $imgSave = " " . $task . " " . $imgSave;
        return $imgSave;
    }

    /*!
     \brief       Gets options for the 'adjust baseline' task
     \param       $task A task from the taskID array that should be 'adjbl'.
     \return      Tcl list with the 'Adjust baseline' task and its options
    */
    private function getTaskIDAdjbl($task) {
        $adjbl = "enabled 0 ni 0";
        $adjbl = $this->string2tcllist($adjbl);
        $adjbl = " " . $task . " " . $adjbl;
        return $adjbl;
    }

    /*!
     \brief       Gets options for the 'algorithm' task. All channels.
     \return      Deconvolution 'algorithm' task string and its options.
    */
    private function getTaskIDAlgorithms( ) {
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
     \brief       Gets options for the 'algorithm' task. One channel.
     \param       $channel A channel
     \return      Tcl list with the deconvolution 'algorithm' task and its options
    */
    private function getTaskAlgorithm($channel) {
        $options = "q ";
        $options .= $this->getQualityFactor();
        $options .= " brMode ";
        $options .= $this->getBrMode();
        $options .= " it ";
        $options .= $this->getIterations();
        $options .= " bgMode ";
        $options .= $this->getBgMode();
        $options .= " bg ";
        $options .= $this->getBgValue($channel);
        $options .= " sn ";
        $options .= $this->getSnrValue($channel);
        $options .= " blMode ";
        $options .= $this->getBleachingMode();
        $options .= " pad ";
        $options .= $this->getPaddingMode();
        $options .= " mode ";
        $options .= $this->getIterationMode();
        $options .= " psfMode ";
        $options .= $this->getPsfMode();
        $options .= " psfPath ";
        $options .= $this->getPsfPath($channel);
        $options .= " timeOut ";
        $options .= $this->getAlgTimeOut();
        return $this->string2tcllist($options);
    }

    /*!
     \brief       Gets the deconvolution timeout
     \return      The timeout in seconds
    */
    private function getAlgTimeOut( ) {
        $algTimeOut = 36000;
        return $algTimeOut;
    }

    /*!
     \brief       Gets options for the 'zdrift' task.
     \params      A task from the taskID array that should be 'zdrift'.
     \return      Tcl list with the 'zdrift' task and its options
     \todo        zDrift option to be implemented in the GUI
    */
    private function getTaskIDZDrift($task) {
        $zdrift = "enabled 1 survey single chan 0 ".
            "filter median filterSize 3";
        $zdrift = $this->string2tcllist($zdrift);
        $zdrift = " " . $task . " " . $zdrift;
        return $zdrift;
    }

    /* -------------------------- Setp tasks ----------------------------------- */
    
    /*!
     \brief       Gets the position of the coverslip.
     \return      Position of the coverslip
     \todo        To be implemented in the GUI
    */
    private function getiFacePrim( ) {
        return $this->string2tcllist("not available");
    }

    /*!
     \brief       Confidence level of iFacePrim.
     \return      Default
     \todo        To be implemented in the GUI
    */
    private function getiFacePrimConfidence( ) {
        return "default";
    }

    /*!
     \brief       Gets the position of the preparation glass.
     \return      Position of the preparation glass
     \todo        To be implemented in the GUI
    */
    private function getiFaceScnd( ) {
        return $this->string2tcllist("not available");
    }

    /*!
     \brief       Confidence level of iFaceScnd.
     \return      Default
     \todo        To be implemented in the GUI
    */
    private function getiFaceScndConfidence( ) {
        return "default";
    }

    /*!
     \brief       Gets the pinhole radii. All channels.
     \return      Tcl list with the Pinhole radii.
    */
    private function getPinholeRadius( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $prList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $pinRadius = $this->getPinRadiusForChannel($chanCnt);
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
        $prList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $confidence = $this->getConfidenceOfPinholeRadiusForChannel($chanCnt);
            $prList .= "  " . $confidence;
        }
        return $this->string2tcllist($prList);
    }

    /*!
     \brief       Confidence level of the pinhole radius. One channel.
     \param       $channel A channel
     \return      Whether verified, reported, estimated or default.
    */
    private function getConfidenceOfPinholeRadiusForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize");
        $format = $this->HuImageFormat;
        
        return $pinholeSize->interpretedHuCoreConfidenceLevel($format,$channel);
    }

    /*!
     \brief       Gets the microscope type of each channel. All channels.
     \return      The microscope type list
    */
    private function getMicroscopeType( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $microList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $microType = $this->getMicroTypeForChannel($chanCnt);
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
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getConfidenceOfMicroTypeForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Confidence level of the microscope type. One channel.
     \param       $channel A channel
     \return       Whether verified, reported, estimated or default.
    */
    private function getConfidenceOfMicroTypeForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("MicroscopeType");
        $format = $this->HuImageFormat;
        
        return $pinholeSize->interpretedHuCoreConfidenceLevel($format,$channel);
    }

    /*!
     \brief       Gets the refractive indexes of all channels.
     \return      Tcl-list with the refractive indexes.
    */
    private function getLensRefractiveIndex( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $lensRIList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $lensRI = $this->getLensRIForChannel($chanCnt);
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
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getConfidenceOfLensRIndexForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Confidence level of the lens refractive index One channel.
     \param       $channel A channel
     \return      Whether verified, reported, estimated or default.
    */
    private function getConfidenceOfLensRIndexForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("ObjectiveType");
        $format = $this->HuImageFormat;
        
        return $pinholeSize->interpretedHuCoreConfidenceLevel($format,$channel);
    }

    /*!
     \brief       Gets the numerical apertures. All channels.
     \return      Tcl-list with the numerical apertures.
    */
    private function getNumericalAperture( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $numAperList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $numAper = $this->getNumApertureForChannel($chanCnt);
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
     \brief       Verification of the numerical apertures. All channels.
     \return      Tcl-list: Trues/Falses if the numerical apertures are verified.
     \todo        To be implemented in the GUI
    */
    private function getNumericalAVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getNAVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Verification of the numerical aperture. One channel.
     \param       $channel A channel
     \return      True if the numerical aperture is verified.
     \todo        To be implemented in the GUI
    */
    private function getNAVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the pinhole spacing. All channels.
     \return      Tcl-list with pinhole spacing.
    */
    private function getPinholeSpacing( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $pinsList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $pinSpacing = $this->getPinSpacingForChannel($chanCnt);
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

        if ($this->getMicroTypeForChannel($channel) != "nipkow") {
            $pinSpacing = "";
        }
        return $pinSpacing;
    }

    /*!
     \brief       Verification of the pinhole spacing. All channels.
     \return      Tcl-list: Trues/Falses if the pinhole spacing is verified.
     \todo        To be implemented in the GUI
    */
    private function getPinSpacingVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getPSVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }


    /*!
     \brief       Verification of the pinhole spacing. One channel.
     \param       $channel A channel
     \return      True if the pinhole spacing is verified.
     \todo        To be implemented in the GUI
    */
    private function getPSVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the sampling sizes. All channels.
     \return      Tcl-list with the sampling sizes.
    */
    private function getSamplingSizes( ) {
        $sampling = $this->microSetting->sampleSizeX();
        $sampling .= " " . $this->microSetting->sampleSizeY();
        $sampling .= " " . $this->microSetting->sampleSizeZ();
        $sampling .= " " . $this->microSetting->sampleSizeT();
        return $this->string2tcllist($sampling);
    }

    /*!
     \brief       Verification of the sampling sizes. All channels.
     \return      Tcl-list: Trues/Falses if the sampling sizes are verified.
     \todo        To be implemented in the GUI
    */
    private function getSamplingVerification( ) {
        if ($this->isFieldVerified(0) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the objective qualities. All channels.
     \return      Tcl-list with the objective qualities.
    */
    private function getObjQuality( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $objQList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $objQuality = $this->getObjQualityForChannel($chanCnt);
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
        $objQuality = "perfect";
        return $objQuality;
    }

    /*!
     \brief       Verification of the objective qualities. All channels.
     \return      Tcl-list: Trues/Falses if the objective qualities are verified.
     \todo        To be implemented in the GUI
    */
    private function getObjQualityVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getOQVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Verification of the Objective Quality. One channel.
     \param       $channel A channel
     \return      True if the Objective Quality is verified.
     \todo        To be implemented in the GUI
    */
    private function getOQVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
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
     \brief       Verification of the excitation photon count. All channels.
     \return      Tcl-list: Trues/Falses if the EP counts are verified.
     \todo        To be implemented in the GUI
    */
    private function getExcitationPcntVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getECntVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Verification of the excitation photon count. One channel.
     \param       $channel A channel
     \return      True if the Excitation Photon count is verified.
     \todo        To be implemented in the GUI
    */
    private function getECntVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the excitation wavelengths. All channels.
     \return      Tcl-list with the excitation wavelengths.
    */
    private function getExcitationLambda( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $exLambdaList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $exLambda = $this->getExLambdaForChannel($chanCnt);
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
     \brief       Verification of the excitation wavelength. All channels.
     \return      Tcl-list: Trues/Falses if the EWs are verified.
     \todo        To be implemented in the GUI
    */
    private function getExcitationLVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getELVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Verification of the excitation wavelength . One channel.
     \param       $channel A channel
     \return      True if the Excitation wavelength is verified.
     \todo        To be implemented in the GUI
    */
    private function getELVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }
    
    /*!
     \brief       Gets the excitation beam overfill factor. All channels.
     \return      Tcl-list with the excitation beam overfill factors.
    */
    private function getExBeamFill( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $exBeamList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $exBeam = $this->getExBeamForChannel($chanCnt);
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
     \brief       Gets the excitation beam factor verification. All channels.
     \return      Tcl-list: Trues/Falses if the EB factors are verified.
     \todo        To be implemented in the GUI
    */
    private function getExBeamVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getEBVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Verification of the EB factor. One channel.
     \param       $channel A channel
     \return      True if the Excitation beam factor is verified.
     \todo        To be implemented in the GUI
    */
    private function getEBVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the emission wavelengths. All channels.
     \return      Tcl-list with the emission wavelengths.
    */
    private function getEmissionLambda( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $emLambdaList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $emLambda = $this->getEmLambdaForChannel($chanCnt);
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
     \brief       Gets the emission lambda verification. All channels.
     \return      Tcl-list: Trues/Falses if the Emission lambdas are verified.
     \todo        To be implemented in the GUI
    */
    private function getEmissionLVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getEMVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Verification of the emission lambda. One channel.
     \param       $channel A channel
     \return      True if the emission lambda is verified.
     \todo        To be implemented in the GUI
    */
    private function getEMVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the imaging direction. All channels.
     \return      Tcl list with the 'imaging direction'.
    */
    private function getImagingDirection( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $dirList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $imagingDir = $this->getImagingDirForChannel($chanCnt);
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
     \brief       Gets the imaging direction verifications. All channels.
     \return      Tcl-list: Trues/Falses if the imaging directions are verified.
     \todo        To be implemented in the GUI
    */
    private function getImagingDirVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getIDVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }
    
    /*!
     \brief       Gets the verification of the imaging direction. One channel.
     \param       $channel A channel
     \return      True if the imaging direction is verified.
     \todo        To be implemented in the GUI
    */
    private function getIDVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief       Gets the medium refractive index. All channels.
     \return      Tcl list with the 'medium refractive indexes'.
    */
    private function getMediumRefractiveIndex() {
        $numberOfChannels = $this->getNumberOfChannels();
        $MRIndexList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $MRIndex = $this->getMRIndexForChannel($chanCnt);
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
     \brief       Gets the medium refractive index verifications. All channels.
     \return      Tcl-list: Trues/Falses if the medium RIs are verified.
     \todo        To be implemented in the GUI
    */
    private function getMediumRIndexVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getMRIVerificationForChannel($chanCnt);
            $verificationList .= " " . $verification;
        }
        return $this->string2tcllist($verificationList);
    }

    /*!
     \brief       Gets the medium refractive index verification. One channel.
     \param       $channel A channel
     \return      True if the medium refractive index is verified.
     \todo        To be implemented in the GUI
    */
    private function getMRIVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /* -------------------------- Algorithm tasks ------------------------------ */
    
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
     \brief       Gets the bleaching mode.
     \return      Bleaching mode
     \todo        To be implemented in the GUI
    */
    private function getBleachingMode( ) {
        $blMode = "auto";
        return $blMode;
    }

    /*!
     \brief       Gets the padding mode.
     \return      Padding mode
     \todo        To be implemented in the GUI
    */
    private function getPaddingMode( ) {
        $padding = "auto";
        return $padding;
    }

    /*!
     \brief       Gets the iteration mode.
     \return      Iteration mode
     \todo        To be implemented in the GUI
    */
    private function getIterationMode( ) {
        $iMode = "fast";
        return $iMode;
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
            $psf = $this->getSrcDir() ."/". $psfFiles[$channel];
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

    /* ------------------------------ Thumbnails -------------------------------- */

    /*!
     \brief       Gets task information for XY and XZ previews
     \param       $key     A task key from the taskID array
     \param       $task    A task from the taskID array
     \param       $srcImg  Whether the raw image or the deconvolved
     \param       $destDir Whether to be saved in the source or the destination
     \return      The preview generation list
    */
    private function getTaskIDXYXZ($key,$task,$srcImg,$destDir,$lif = null) {

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
    private function getTaskIDOrtho($key,$task,$srcImg,$destDir) {

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
    private function getTaskIDSFP($key,$task,$srcImg,$destDir) {

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
    private function getTaskIDMovie($key,$task,$srcImg,$destDir,$movieType) {

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
    private function getTaskIDZComparison($key,$task,$destDir) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }

        $previewGen = " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= basename($this->destImage);
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
    private function getTaskIDTComparison($key,$task,$destDir) {

        /* Get the Huygens task name of the $task */
        $task = $this->parseTask($key,$task);
        if ($task == "") {
            return;
        }

        $previewGen = " destDir ";
        $previewGen .= $this->getThumbnailDestDir($destDir);
        $previewGen .= " destFile ";
        $previewGen .= basename($this->destImage);
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
        return $destDir;
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

        switch ( $caller ) {
        case 'XYXZ':
            if ($srcImg == "raw") {
                $destFile = basename($this->srcImage);
                $destFile .= $this->getLifImageSuffix($lif);
                $destFile = $this->string2tcllist($destFile);
            } elseif ($srcImg == "dec") {
                $destFile = basename($this->destImage);
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'Ortho':
            if ($srcImg == "raw") {
                $destFile = basename($this->destImage) . ".original";
            } elseif ($srcImg == "dec") {
                $destFile = basename($this->destImage);
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'SFP':
            if ($srcImg == "raw") {
                $destFile = basename($this->destImage) . ".original.sfp";
            } elseif ($srcImg == "dec") {
                $destFile = basename($this->destImage) . ".sfp";
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        case 'Movie':
            if ($srcImg == "raw") {
                $destFile = basename($this->srcImage);
            } elseif ($srcImg == "dec") {
                $destFile = basename($this->destImage);
                $destFile .= $this->getMovieFileSuffix($movieType);
            } else {
                error_log("Unknown image source: $srcImg");
            }
            break;
        default:
            error_log("Unknown thumbnail caller: $caller");
        }
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
     \brief       Resets the counter of preview tasks
    */
    private function initializePreviewCounter( ) {
        $this->previewCnt = 0;
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
        $today = date('j-m-y');  
        $templateName = "batch_" . $today . "_" . $time;
        return $templateName;
    }

    /*!
     \brief       Gets the Huygens template version built in this class
     \return      The template version
    */
    private function getTemplateVersion( ) {
        return $templateVersion = 2.2;
    }

    /*!
     \brief       Gets a general template title
     \return      The template title
    */
    private function getTemplateTitle( ) {
        $templateTitle = "Batch processing template";
        return $this->string2tcllist($templateTitle);
    }

    /*!
     \brief       Gets the number of channels of the template.
     \return      Number of channels.
    */
    private function getNumberOfChannels( ) {
        return $this->microSetting->numberOfChannels();
    }

    /*!
     \brief       Checks whether a field is verified.
     \param       $field A field to check
     \return      True if the field is verified.
     \todo        Implement the body of this function.
    */
    private function isFieldVerified($field) {
        return 1;
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
            $this->subImage = $this->string2tcllist($match[2]);
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
        $this->destImage = str_replace(" ","_",$this->destImage);
    }

    /*!
     \brief       Sets the image format as coded in the HuCore confidence level
     \brief       table, whether leica, r3d, etc.
     \return      An image format.
    */
    private function setHuImageFormat( ) {
        $this->HuImageFormat = $this->getHuImageFormat();
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