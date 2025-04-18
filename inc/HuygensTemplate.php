<?php
/**
 * HuygensTemplate
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

use hrm\job\JobDescription;
use hrm\param\ChromaticAberration;
use hrm\param\OutputFileFormat;
use hrm\setting\AnalysisSetting;
use hrm\setting\ParameterSetting;
use hrm\setting\TaskSetting;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Converts deconvolution parameters into a Huygens batch template.
 *
 * This class builds Tcl-compliant nested lists which summarize the tasks and
 * properties of the deconvolution job. Tasks describing the thumbnail products
 * of an HRM deconvolution job are also included. The resulting structure is
 * a Huygens batch template formed by nested lists.
 *
 * Template structure:
 * - 1 Job:
 *     - Job info.
 *     - Job tasks list:
 *         - Set environment
 *         - Set task ID
 *     - Set environment:
 *         - resultDir
 *         - perJobThreadCnt
 *         - concurrentJobCnt
 *         - exportFormat
 *         - timeOut
 *     - Set image processing list:
 *         - Set image processing info:
 *             - state
 *             - tag
 *             - timeStartAbs
 *             - timeOut
 *         - Set image processing subtasks:
 *             - imgOpen
 *             - setp
 *             - deconvolution algorithm: one per channel
 *             - previewGen (one per thumbnail type)
 *             - imgSave
 *         - Set subtasks details (imgOpen, setp,..)
 */
class HuygensTemplate
{

    /**
     * Batch template containing the deconvolution job + thumbnail tasks.
     * @var string
     */
    public $template;

    /**
     * Array with informaton on the template job info tag.
     * @var array
     */
    private $jobInfoArray;

    /**
     * A Tcl list with job information for the template info tag.
     * @var string
     */
    private $jobInfoList;

    /**
     * Array with information on the job's main tasks.
     * @var array
     */
    private $jobTasksArray;

    /**
     * A Tcl list with information on the job's main tasks.
     * @var string
     */
    private $jobTasksList;

    /**
     * Array with information on the setEnv task.
     * @var array
     */
    private $envArray;

    /**
     * A Tcl list with environment data: number of cores, timeout, etc.
     * @var string
     */
    private $envList;

    /**
     * Array with restoration and thumbnail-related operations.
     * @var array
     */
    private $imgProcessArray;

    /**
     * A Tcl list with restoration and thumbnail operations.
     * @var string
     */
    private $imgProcessList;

    /**
     * Array with information on the info tag of the img process task.
     * @var array
     */
    private $imgProcessInfoArray;

    /**
     * Array with information on the subtasks of the img process task.
     * @var array
     */
    private $imgProcessTasksArray;

    /**
     * Array with information on export format tag of the setenv task.
     * @var array
     */
    private $expFormatArray;

    /**
     * Array with information on the image open subtask.
     * @var array
     */
    private $imgOpenArray;

    /**
     * Array with information on the image save subtask.
     * @var array
     */
    private $imgSaveArray;

    /**
     * Array with information on the autocrop subtask.
     * @var array
     */
    private $autocropArray;

    /**
     * Array with information on the Z stabilize subtask.
     * @var array
     */
    private $ZStabilizeArray;

    /**
     * Array with information on the image adjbl subtask.
     * @var array
     */
    private $adjblArray;

    /**
     * Array with information on the image chromatic aberration subtask.
     * @var array
     */
    private $chromaticArray;

    /**
     * Array with information on the available deconvolution algorithms.
     * @var array
     */
    private $deconArray;

    /**
     * Array with information on the channel cmle/qmle/gmle/skip subtask.
     * @var array
     */
    private $algArray;

    /**
     * Array with information on the time series stabilize subtask.
     * @var array
     */
    private $TStabilizeArray;

    /**
     * Array with information on the colocalization analysis subtask.
     * @var  array
     */
    private $colocArray;

    /**
     * Array with information on the 2D histogram subtask.
     * @var array
     */
    private $histoArray;

    /**
     * Array with information on the set parameter 'setp' subtask.
     * @var array
     */
    private $setpArray;

    /**
     * Array with information on the parameter confidence levels.
     * @var array
     */
    private $setpConfArray;

    /**
     * Array with information on the hot pixel correction subtask.
     * @var array
     */
    private $hpcArray;

    /**
     * Array with information on thumbnail projections.
     * @var array
     */
    private $thumbArray;

    /**
     * A Tcl list with information for the 'set parameter' subtask.
     * @var string
     */
    private $setpList;

    /**
     * A Tcl list with information for the 'hot pixel correction' subtask.
     * @var string
     */
    private $hpcList;

    /**
     * Path and name of the source 'raw' image.
     * @var string
     */
    private $srcImage;

    /**
     * Path and name of the deconvolved image.
     * @var string
     */
    private $destImage;

    /**
     * JobDescription object: unformatted microscopic & restoration data.
     * @var JobDescription
     */
    private $jobDescription;

    /**
     * A ParametersSetting object: unformatted microscopic parameters.
     * @var ParameterSetting
     */
    private $microSetting;

    /**
     * A TaskSetting object: unformatted restoration parameters.
     * @var TaskSetting
     */
    private $deconSetting;

    /**
     * An AnalysisSetting object: unformatted analysis parameters.
     * @var AnalysisSetting
     */
    private $analysisSetting;

    /**
     * A boolean to know whether a Z slicer will be created.
     * @var bool
     */
    public $compareZviews;

    /**
     * A boolean to know whether a T slicer will be created.
     * @var bool
     */
    public $compareTviews;

    /**
     * An integer that keeps track of the number of thumbnail tasks.
     * @var int
     */
    private $thumbCnt;

    /**
     * Whether to make a thumbnail from the raw or from the decon image.
     * @var string
     */
    private $thumbFrom;

    /**
     * Whether the thumbnail is to be saved in the src or dest folder.
     * @var string
     */
    private $thumbToDir;

    /**
     * Whether to make an XYXZ, Ortho, SFP, Movie, etc, type of thumbnail.
     * @var string
     */
    private $thumbType;

    /**
     * Whether to make thumbnails for lif, lof and czi sub images.
     * @var string
     */
    private $thumbSubImg;

    /**
     * The ID of the GPU card where to run this job.
     * @var string
     */
    private $gpuId;
    

    /* -------------------------- Constructor ------------------------------- */

    /**
     * Constructor
     * @param JobDescription $jobDescription JobDescription object
     */
    public function __construct(JobDescription $jobDescription)
    {
        $this->initialize($jobDescription);
        $this->setJobInfoList();
        $this->setJobTasksList();
        $this->setEnvList();
        $this->setImgProcessList();
        $this->assembleTemplate();
    }

    /* ------------------------- Initialization ------------------------------ */

    /**
     * Sets general class properties to initial values
     * @param JobDescription $jobDescription JobDescription object.
     */
    private function initialize(JobDescription $jobDescription)
    {
        $this->jobDescription = $jobDescription;
        $this->microSetting = $jobDescription->parameterSetting();
        $this->deconSetting = $jobDescription->taskSetting();
        $this->analysisSetting = $jobDescription->analysisSetting();
        $this->gpuId = $jobDescription->gpu();

        $this->initializeImg();
        $this->initializeThumbCounter();
        $this->initializeJobInfo();
        $this->initializeJobTasks();
        $this->initializeEnvironment();
        $this->initializeImgProcessing();
    }

    /**
     * Sets names and paths of the raw and deconvolved images.
     */
    private function initializeImg()
    {
        $this->setSrcImage();
        $this->setDestImage();
    }

    /**
     * Resets the counter of thumbnail tasks.
     */
    private function initializeThumbCounter()
    {
        $this->thumbCnt = 0;
    }

    /**
     * Loads an array containing information on the job info tag.
     */
    private function initializeJobInfo()
    {

        $this->jobInfoArray =
            array('title'        => 'Batch Processing template',
                  'version'      => '2.4',
                  'templateName' => '',
                  'date'         => '',
                  'listID'       => 'info');
    }

    /**
     * Loads an array containing information on the job's main tasks.
     */
    private function initializeJobTasks()
    {

        $this->jobTasksArray =
            array('setEnv'   => '',
                  'taskID:0' => '',
                  'listID'   => 'taskList');
    }

    /**
     * Loads arrays with environment data: number cores, timeout, ..
     */
    private function initializeEnvironment()
    {
        $this->envArray =
            array('resultDir'        => '',
                  'perJobThreadCnt'  => 'auto',
                  'concurrentJobCnt' => '1',
                  'OMP_DYNAMIC'      => '1',
                  'timeOut'          => '100000',
                  'exportFormat'     => '',
                  'gpuDevice'        => '0',
                  'listID'           => 'setEnv');

        $this->expFormatArray =
            array('type'     => '',
                  'multidir' => '',
                  'cmode'    => 'scale');
    }

    /**
     * Loads arrays with the job's tasks and subtasks.
     */
    private function initializeImgProcessing()
    {

        $this->imgProcessArray =
            array('info'     => '',
                  'taskList' => '',
                  'listID'   => 'taskID:0');


        /* As long as the Huygens Scheduler receives only one job at a time
         the task will not be queued when it arrives at the Scheduler but
         executed immediatly, thus its state will be 'readyToRun', invariably. */

        /* All metadata will be accepted as long as the template counterparts
         don't exist. The accepted confidence level is therefore: "default". */

        /* There are no specific names for the deconvolution and microscopy
         templates in the Tcl-lists, they will be set to general names. */
        $this->imgProcessInfoArray =
            array('state'             => 'readyToRun',
                  'tag'               => '{setp Micr decon Decon}',
                  'timeStartAbs'      => '',
                  'timeOut'           => '10000',
                  'userDefConfidence' => 'default',
                  'listID'            => 'info');
        
        /* These are the operations carried out by HuCore/HRM. Operations for
           thumbnail generation are included. Notice that the names of the
           thumbnail operations contain the destination directory as well as
           the thumbnail type and the image type. The thumbnail operation
           names code the type of action executed on the image.*/
        $this->imgProcessTasksArray =
            array('open'                  => 'imgOpen',
                'setParameters'           => 'setp',
		        'hotPixelCorrection'      => 'hotPix',
                'autocrop'                => 'autocrop',
                'adjustBaseline'          => 'adjbl',
                'ZStabilization'          => 'stabilize',
                'algorithms'              => '',
                'TStabilization'          => 'stabilize:post',
                'colocalization'          => 'coloc',
                'chromatic'               => 'shift',
                '2Dhistogram'             => 'hist',
                'XYXZRawAtSrcDir'         => 'previewGen',
                'XYXZRawSubImgAtSrcDir'   => 'previewGen',
                'XYXZRawAtDstDir'         => 'previewGen',
                'XYXZDecAtDstDir'         => 'previewGen',
                'orthoRawAtDstDir'        => 'previewGen',
                'orthoDecAtDstDir'        => 'previewGen',
                'ZMovieDecAtDstDir'       => 'previewGen',
                'TimeMovieDecAtDstDir'    => 'previewGen',
                'TimeSFPMovieDecAtDstDir' => 'previewGen',
                'SFPRawAtDstDir'          => 'previewGen',
                'SFPDecAtDstDir'          => 'previewGen',
                'ZComparisonAtDstDir'     => 'previewGen',
                'TComparisonAtDstDir'     => 'previewGen',
                'save'                    => 'imgSave',
                'listID'                  => 'taskList');

        /* Options for the 'open image' action */
        $this->imgOpenArray =
            array('path'   => '',
                  'subImg' => '',
                  'series' => '',
                  'index'  => '0',
                  'listID' => 'imgOpen');
        
        /* Options for the 'set image parameter' action */
        $this->setpArray =
            array('completeChanCnt'  => '',
                  'micr'             => '',
                  's'                => '',
                  'iFacePrim'        => '0.0',
                  'iFaceScnd'        => '0.0',
                  'pr'               => '',
                  'imagingDir'       => '',
                  'ps'               => '',
                  'objQuality'       => 'good',
                  'pcnt'             => '',
                  'ex'               => '',
                  'em'               => '',
                  'exBeamFill'       => '2.0',
                  'ri'               => '',
                  'ril'              => '',
                  'na'               => '',
                  'stedMode'         => '',
                  'stedLambda'       => '',
                  'stedSatFact'      => '',
                  'stedImmunity'     => '',
                  'sted3D'           => '',
                  'spimExcMode'      => '',
                  'spimGaussWidth'   => '',
                  'spimCenterOffset' => '',
                  'spimFocusOffset'  => '',
                  'spimNA'           => '',
                  'spimFill'         => '',
                  'spimDir'          => '',
                  'listID'           => 'setp');

        /* Options for the 'set image pararmeter' action */
        $this->setpConfArray =
            array('completeChanCnt'  => '',
                  'micr'             => 'parState,micr',
                  's'                => 'parState,s',
                  'iFacePrim'        => 'parState,iFacePrim',
                  'iFaceScnd'        => 'parState,iFaceScnd',
                  'pr'               => 'parState,pr',
                  'imagingDir'       => 'parState,imagingDir',
                  'ps'               => 'parState,ps',
                  'objQuality'       => 'parState,objQuality',
                  'pcnt'             => 'parState,pcnt',
                  'ex'               => 'parState,ex',
                  'em'               => 'parState,em',
                  'exBeamFill'       => 'parState,exBeamFill',
                  'ri'               => 'parState,ri',
                  'ril'              => 'parState,ril',
                  'na'               => 'parState,na',
                  'stedMode'         => 'parState,stedMode',
                  'stedLambda'       => 'parState,stedLambda',
                  'stedSatFact'      => 'parState,stedSatFact',
                  'stedImmunity'     => 'parState,stedImmunity',
                  'sted3D'           => 'parState,sted3D',
                  'spimExcMode'      => 'parState,spimExcMode',
                  'spimGaussWidth'   => 'parState,spimGaussWidth',
                  'spimCenterOffset' => 'parState,spimCenterOffset',
                  'spimFocusOffset'  => 'parState,spimFocusOffset',
                  'spimNA'           => 'parState,spimNA',
                  'spimFill'         => 'parState,spimFill',
                  'spimDir'          => 'parState,spimDir',
                  'listID'           => 'setp');

        /* Options for the 'hot pixel correction' action */
        $this->hpcArray =
            array('hotPath'  => '',
                  'timeOut'  => '10000',
                  'listID'   => 'hotPix');


        /* Options for the 'adjust baseline' action */
        $this->adjblArray =
            array('enabled' => '0',
                  'ni'      => '0',
                  'listID'  => 'adjbl');

        /* Options for the 'chromatic aberration correction' action */
        $this->chromaticArray =
            array('q'          => 'standard',
                  'vector'     => '',
                  'reference'  => '',
                  'channel'    => '',
                  'lambdaEm'   => '480',
                  'lambdaEx'   => '480',
                  'lambdaSted' => '480',
                  'mType'      => 'generic',
                  'estMethod'  => '2',
                  'listID'     => 'shift');

        /* Supported deconvolution algorithms. */          
        $this->deconArray = array('cmle'  => 'cmle', 
                                  'qmle'  => 'qmle',
                                  'gmle'  => 'gmle',
                                  'skip'  => 'deconSkip');

        /* Options for the 'execute deconvolution' action */
        $this->algArray =
            array('q'          => '',
                  'brMode'     => '',
                  'varPsf'     => '',
                  'it'         => '',
                  'bgMode'     => '',
                  'bg'         => '',
                  'snr'        => '',
                  'acuity'     => '',
                  'acuityMode' => '',
                  'blMode'     => 'auto',
                  'pad'        => 'auto',
                  'reduceMode' => 'auto',
                  'psfMode'    => '',
                  'psfPath'    => '',
                  'timeOut'    => '36000',
                  'mode'       => 'fast',
                  'itMode'     => 'auto',
                  'listID'     => '');

        /* Options for the 'autocrop' action. */
        $this->autocropArray =
            array('enabled' => '0',
                  'listID'  => 'autocrop');

        /* Options for the 'ZStabilization' action. */
        $this->ZStabilizeArray =
            array('enabled' => '0',
                  'listID'  => 'stabilize');

        /* Options for the 'TStabilization' action. */
        $this->TStabilizeArray =
            array('enabled' => '0',
                  'mode'    => '',
                  'rot'     => '',
                  'crop'    => '',
                  'listID'  => 'stabilize:post');

        /* Options for the 'colocalization analysis' action. */
        $this->colocArray =
            array('chanR'        => '',
                  'chanG'        => '',
                  'threshMode'   => '',
                  'threshPercR'  => '',
                  'threshPercG'  => '',
                  'coefficients' => '',
                  'map'          => '',
                  'destDir'      => '',
                  'destFile'     => '',
                  'listID'       => 'coloc');

        /* Options for the '2D histogram' action. */
        $this->histoArray =
            array('chanR'    => '',
                  'chanG'    => '',
                  'destDir'  => '',
                  'destFile' => '',
                  'listID'   => 'hist');

        /* Options for the 'create thumbnail from image' action. */
        $this->thumbArray =
            array('image'    => '',
                  'destDir'  => '',
                  'destFile' => '',
                  'type'     => '',
                  'size'     => '400');

        /* Options for the 'save image' action. */
        $this->imgSaveArray =
            array('rootName' => '',
                  'listID'   => 'imgSave');

        /* Check whether the image is manageable to create slices from it. */
        $this->isEligibleForSlicers($this->srcImage);
    }

    /* --------------------- Main task list builders ------------------------- */

    /**
     * Puts the Huygens Batch template together.
     */
    private function assembleTemplate()
    {
        $this->template = $this->jobInfoList . "\n";
        $this->template .= $this->jobTasksList . "\n";
        $this->template .= $this->envList . "\n ";
        $this->template .= $this->imgProcessList;
    }

    /**
     * Sets the template info tag.
     */
    private function setJobInfoList()
    {

        $list = "";

        foreach ($this->jobInfoArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }

            switch ($key) {
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
                    Log::error("Job info field $key not yet implemented.");
            }
        }
    }

    /**
     * Sets the template's main tasks: setEnv and taskIDs.
     */
    private function setJobTasksList()
    {

        $list = "";

        foreach ($this->jobTasksArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }

            switch ($key) {
                case 'setEnv':
                case 'taskID:0':
                    break;
                case 'listID':
                    $list = $this->string2tcllist($list);
                    $this->jobTasksList = $value . " " . $list;
                    break;
                default:
                    Log::error("Job task $key not yet implemented.");
            }
        }
    }

    /**
     * Sets the job's environment task: number of cores, timeout, etc.
     */
    private function setEnvList()
    {

        $list = "";
        
        foreach ($this->envArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }

            switch ($key) {
                case 'resultDir':
                    $list .= $this->string2tcllist($this->getDestDir());
                    break;
                case 'exportFormat':
                    $list .= $this->getExportFormat();
                    break;
                case 'gpuDevice':
                    $list .= $this->gpuId;
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
                    Log::error("Environment field $key not yet implemented");
            }
        }
    }

    /**
     * Sets the template's restoration and thumbnail operations.
     */
    private function setImgProcessList()
    {

        $list = "";

        foreach ($this->imgProcessArray as $key => $value) {

            if ($key != "listID") {
                $list .= " ";
            }

            switch ($key) {
                case 'info':
                    $list .= $this->getImgProcessInfoList();
                    break;
                case 'taskList':
                    $list .= $this->getImgProcessTaskList();
                    $list .= $this->getImgProcessTasksDescr();
                    break;
                case 'listID':
                    $list = $this->string2tcllist($list);
                    $this->imgProcessList = $value . " " . $list;
                    break;
                default:
                    Log::error("Image processing task $key not yet implemented");
            }
        }
    }

    /* ------------------- Secondary task list builders ---------------------- */

    /**
     * Gets information on the template's only job.
     * @return string The Tcl-compliant nested list with the info details.
     */
    private function getImgProcessInfoList()
    {

        $list = "";

        foreach ($this->imgProcessInfoArray as $key => $value) {

            if ($key != "listID") {
                $list .= " " . $key . " ";
            }

            switch ($key) {
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
                    Log::error("Info option $key not yet implemented");
            }
        }

        return $list;
    }

    /**
     * Gets the Huygens subtask names of the deconvolution process.
     * @return string The Tcl-compliant nested list with subtask names.
     */
    private function getImgProcessTaskList()
    {

        $list = "";

        foreach ($this->imgProcessTasksArray as $key => $value) {
	    if ($key == 'hotPixelCorrection' && !$this->hotPixelMaskExists()) {
	        continue;
            }
            switch ($key) {
                case 'open':
                case 'save':
                case 'setParameters':
		case 'hotPixelCorrection':
                case 'autocrop':
                case 'adjustBaseline':
                case 'ZStabilization':
                case 'algorithms':
                case 'chromatic':
                case 'TStabilization':
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
                    $task = $this->getTaskName($key, $value);
                    if ($task != "") {
                        $list .= $task . " ";
                    }
                    break;
                case 'listID':
                    $list = $this->string2tcllist($list);
                    $list = $value . " " . $list;
                    break;
                default:
                    Log::error("Image process task $key not yet implemented");
            }
        }

        return $list;
    }

    /**
     * Gets details of specific deconvolution subtasks.
     * @return string The Tcl-compliant nested list with subtask details.
     */
    private function getImgProcessTasksDescr()
    {

        $tasksDescr = "";

        $this->initializeThumbCounter();
        foreach ($this->imgProcessTasksArray as $key => $value) {
	    if ($key == 'hotPixelCorrection' && !$this->hotPixelMaskExists()) {
	        continue;
            }

            $tasksDescr .= " ";
            switch ($key) {
                case 'open':
                    $tasksDescr .= $this->getImgTaskDescrOpen();
                    break;
                case 'save':
                    $tasksDescr .= $this->getImgTaskDescrSave();
                    break;
                case 'setParameters':
                    $tasksDescr .= $this->getImgTaskDescrSetp();
                    break;
                case 'hotPixelCorrection':
                    $tasksDescr .= $this->getImgTaskDescrHotPixelCorrection();
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
                case 'TStabilization':
                    $tasksDescr .= $this->getImgTaskDescrTStabilize();
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
                    $tasksDescr .= $this->getImgTaskDescrThumbnail($key, $value);
                    break;
                case 'listID':
                    break;
                default:
                    $tasksDescr = "";
                    Log::error("Image processing task $key not yet implemented.");
            }
        }

        return $tasksDescr;
    }

    /**
     * Gets options for the 'image open' task.
     * @return string Tcl list with the'Image open' task and its options.
     */
    private function getImgTaskDescrOpen()
    {

        $taskDescr = "";

        foreach ($this->imgOpenArray as $key => $value) {

            if ($key != "subImg" && $key != 'listID') {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
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
                    $taskDescr = $value . " " . $taskDescr;
                    break;
                default:
                    Log::error("Image open option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /**
     * Gets options for the 'set parameters' task.
     * @return string Tcl list with the 'Set parameters' task and its options.
     */
    private function getImgTaskDescrSetp()
    {

        $taskDescr = "";

        foreach ($this->setpArray as $key => $value) {

            switch ($key) {
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
                case 'spimExcMode':
                case 'spimGaussWidth':
                case 'spimCenterOffset':
                case 'spimFocusOffset':
                case 'spimNA':
                case 'spimFill':
                case 'spimDir':
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $this->setpList = $value . " " . $taskDescr;
                    break;
                default:
                    Log::error("Setp field $key not yet implemented.");
            }

            if ($key != "listID" && $key != "completeChanCnt") {
                $taskDescr .= $this->getParameter($key, $value);
            }
        }

        return $this->setpList;
    }

    /**
     * Gets options for the 'hot pixel correction' task.
     * @return string Tcl list with the 'Hot Pixel Correction' task and its options.
     */
    private function getImgTaskDescrHotPixelCorrection()
    {
        $taskDescr = "";

        /* The HPC mask may be located in a different subfolder than the raw 
           data. Thus, its path must be found independently of the raw images. */
        $userFileArea = $this->jobDescription->sourceFolder();
        $deconSetting = $this->deconSetting;

        foreach ($this->hpcArray as $key => $value) {
            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'hotPath':		
                    $hpcFile = $deconSetting->parameter("HotPixelCorrection")->value();
                    $hpcPath = trim($userFileArea . $hpcFile[0]);
                    $taskDescr .= $this->string2tcllist($hpcPath);
                    break;
		case 'timeOut':
		    $taskDescr .= $value;
                case 'listID':
                    $this->hpcList = $value . " " . $this->string2tcllist($taskDescr);
                    break;
                default:
                    Log::error("Hot pixel correction field $key not yet implemented.");
            }
        }

        return $this->hpcList;
    }

    /**
     * Gets options for the 'adjust baseline' task.
     * @return string Tcl list with the 'Adjust baseline' task and its options.
     */
    private function getImgTaskDescrAdjbl()
    {

        $taskDescr = "";

        foreach ($this->adjblArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'ni':
                case 'enabled':
                    $taskDescr .= $value;
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $taskDescr = $value . " " . $taskDescr;
                    break;
                default:
                    Log::error("Image adjbl option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /**
     * Gets options for the 'chromatic aberration correction' task.
     * @return string Tcl list with the 'chromatic aberration' task and its options.
     */
    private function getImgTaskDescrChromatic()
    {

        $allTasksDescr = "";

        $channelsArray = $this->getChansForChromaticCorrection();
        if (empty($channelsArray)) {
            return $allTasksDescr;
        }
        
        foreach ($channelsArray as $chanKey => $chan) {
            $taskDescr = "";
            /** @var ChromaticAberrationCh$chan $chromaticParam */
            $chromaticParam =
                $this->deconSetting->parameter("ChromaticAberrationCh" . $chan);
            $chanVector = implode(' ', $chromaticParam->value());
            
            foreach ($this->chromaticArray as $chromKey => $chromValue) {
                if ($chromKey != "listID") {
                    $taskDescr .= " " . $chromKey . " ";
                }

                /* Notice that we force a 'sorted' channel correction, i.e.,
                   there's no matching done based on wavelengths, etc. */
                switch ($chromKey) {
                    case 'q':
                    case 'lambdaEm':
                    case 'lambdaEx':
                    case 'lambdaSted':
                    case 'mType':
                        $taskDescr .= $chromValue;
                        break;
                    case 'estMethod':
                        if ($chromaticParam->componentCnt() > 5) {
                            $taskDescr .= '6';
                        } else {
                            $taskDescr .= $chromValue;
                        }
                        break;
                    case 'channel':
                        $taskDescr .= $chan;
                        break;
                    case 'vector':
                        $taskDescr .= $this->string2tcllist($chanVector);
                        break;
                    case 'reference':
                        if (trim($chanVector) == "0 0 0 0 1") {
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
                        Log::error("Image shift option $chromKey not yet implemented.");
                }
            }
        }

        return $allTasksDescr;
    }

    /**
     * Get options for the 'TStabilize' task.
     * @return string Tcl list with the 'TStabilize' task and its options.
     */
    private function getImgTaskDescrTStabilize()
    {

        $taskDescr = "";

        $TStabilizeParam = $this->deconSetting->parameter('TStabilization');
        $TStabilizeMethodParam = $this->deconSetting->parameter('TStabilizationMethod');
        $TStabilizeRotationParam = $this->deconSetting->parameter('TStabilizationRotation');
        $TStabilizeCroppingParam = $this->deconSetting->parameter('TStabilizationCropping');

        foreach ($this->TStabilizeArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'enabled':               
                    $taskDescr .= $TStabilizeParam->value();
                    break;
                case 'mode':               
                    $taskDescr .= $TStabilizeMethodParam->value();
                    break;
                case 'rot':               
                    $taskDescr .= $TStabilizeRotationParam->value();
                    break;
                case 'crop':               
                    $taskDescr .= $TStabilizeCroppingParam->value();
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $taskDescr = $value . " " . $taskDescr;
                    break;
                default:
                    Log::error("Image T stabilize option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }


    /**
     * Get options for the 'Autocrop' task.
     * @return string Tcl list with the 'autocrop' task and its options.
     */
    private function getImgTaskDescrAutocrop()
    {

        $taskDescr = "";

        $autocropParam = $this->deconSetting->parameter('Autocrop');
        foreach ($this->autocropArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'enabled':
                    $taskDescr .= $autocropParam->value();
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $taskDescr = $value . " " . $taskDescr;
                    break;
                default:
                    Log::error("Image autocrop option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /**
     * Get options for the 'ZStabilize' task.
     * @return string Tcl list with the 'ZStabilize' task and its options.
     */
    private function getImgTaskDescrZStabilize()
    {

        $taskDescr = "";

        $stedData = False;
        $chanCnt = $this->getChanCnt();
        for ($chan = 0; $chan < $chanCnt; $chan++) {
            if (strstr($this->getMicroscopeType($chan), 'sted')) {
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
            switch ($key) {
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
                    Log::error("Image Z stabilize option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /**
     * Gets options for the 'algorithm' task. All channels.
     * @return string Deconvolution 'algorithm' task string and its options.
     */
    private function getImgTaskDescrAlgorithms()
    {

        $allTasksDescr = "";

        $chanCnt = $this->getChanCnt();
        for ($chan = 0; $chan < $chanCnt; $chan++) {
            $algorithm = $this->getAlgorithm($chan);
            $taskDescr = $this->getTaskDescrAlgorithm($chan);
            $allTasksDescr .= " ${algorithm}:$chan $taskDescr";
        }

        return $allTasksDescr;
    }

    /**
     * Gets options for all the 'colocalization' tasks.
     * @return string Tcl list with the 'colocalization' tasks and their options.
     */
    private function getImgTaskDescrColocs()
    {

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

                if ($runCnt < $colocRuns) {
                    $runCnt++;
                } else {
                    Log::error("Wrong number of colocalization runs: $runCnt");
                }
            }
        }

        return $allTasksDescr;
    }

    /**
     * Gets options for the '2Dhistogram' task.
     * @return string Tcl list with the '2Dhistogram' task and its options.
     */
    private function getImgTaskDescrHistograms()
    {

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

                if ($runCnt < $colocRuns) {
                    $runCnt++;
                } else {
                    Log::error("Wrong number of colocalization runs: $runCnt");
                }
            }
        }

        return $allTasksDescr;
    }


    /**
     * Gets options for the thumbnail generation task.
     * @todo Document input arguments!
     * @param $thumbType
     * @param $thumbID
     * @return string Tcl-compliant list with the thumbnail options.
     * @todo Set thumbtypes in an array at initialization.
     */
    private function getImgTaskDescrThumbnail($thumbType, $thumbID)
    {

        if (preg_match("/Raw/i", $thumbType)) {
            $this->thumbFrom = "raw";
        } elseif (preg_match("/Dec/i", $thumbType)) {
            $this->thumbFrom = "deconvolved";
        } else {
            $this->thumbFrom = null;
        }

        if (preg_match("/SubImg/i", $thumbType)) {
            $this->thumbSubImg = "subimg";
        } else {
            $this->thumbSubImg = null;
        }

        if (preg_match("/SrcDir/i", $thumbType)) {
            $this->thumbToDir = $this->getSrcDir() . "/hrm_previews";
        } elseif (preg_match("/DstDir/", $thumbType)) {
            $this->thumbToDir = $this->getDestDir() . "/hrm_previews";
        }
        $this->thumbToDir = $this->string2tcllist($this->thumbToDir);

        if (preg_match("/XYXZ/i", $thumbType)) {
            $this->thumbType = "XYXZ";
        } elseif (preg_match("/ortho/i", $thumbType)) {
            $this->thumbType = "orthoSlice";
        } elseif (preg_match("/TimeSFPMovie/i", $thumbType)) {
            $this->thumbType = "timeSFPMovie";
        } elseif (preg_match("/SFP/i", $thumbType)) {
            $this->thumbType = "SFP";
        } elseif (preg_match("/ZMovie/i", $thumbType)) {
            $this->thumbType = "ZMovie";
        } elseif (preg_match("/timeMovie/i", $thumbType)) {
            $this->thumbType = "timeMovie";
        } elseif (preg_match("/ZComparison/i", $thumbType)) {
            $this->thumbType = "compareZStrips";
        } elseif (preg_match("/TComparison/i", $thumbType)) {
            $this->thumbType = "compareTStrips";
        } else {
            $this->thumbType = null;
        }

        $taskDescr = $this->getThumbnailTaskDescr($thumbType, $thumbID);

        return $taskDescr;
    }

    /**
     * Gets options for the 'image save' task.
     * @return string Tcl list with the 'Image save' task and its options.
     */
    private function getImgTaskDescrSave()
    {

        $taskDescr = "";

        foreach ($this->imgSaveArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'rootName':
                    $outName = $this->getDestImageBaseName();
                    $taskDescr .= $this->string2tcllist($outName);
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $taskDescr = " " . $value . " " . $taskDescr;
                    break;
                default:
                    Log::error("Image save option $key not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /* -------------------------- Setp task ---------------------------------- */

    /**
     * Gets the SPIM excitation mode. One channel.
     * @param int $channel A channel.
     * @return string The SPIM excitation mode.
     */
    private function getSpimExcMode($channel)
    {
        $microSetting = $this->microSetting;
        $spimExcMode = $microSetting->parameter("SpimExcMode")->value();
        $spimExcMode = $spimExcMode[$channel];

        return $spimExcMode;
    }

    /**
     * Gets the SPIM Gauss Width. One channel.
     * @param int $channel A channel.
     * @return float The SPIM Gauss Width.
     */
    private function getSpimGaussWidth($channel)
    {
        $microSetting = $this->microSetting;
        $spimGaussWidth = $microSetting->parameter("SpimGaussWidth")->value();
        return $spimGaussWidth[$channel];
    }

    /**
     * Gets the SPIM Center Offset. One channel.
     * @param int $channel A channel.
     * @return float The SPIM Center Offset.
     */
    private function getSpimCenterOffset($channel)
    {
        $microSetting = $this->microSetting;
        $spimCenterOffset = $microSetting->parameter("SpimCenterOffset")->value();
        return $spimCenterOffset[$channel];
    }

    /**
     * Gets the SPIM Focus Offset. One channel.
     * @param  int $channel A channel.
     * @return float The SPIM Focus Offset.
     */
    private function getSpimFocusOffset($channel)
    {
        $microSetting = $this->microSetting;
        $spimFocusOffset = $microSetting->parameter("SpimFocusOffset")->value();
        return $spimFocusOffset[$channel];
    }

    /**
     * Gets the SPIM NA. One channel.
     * @param int $channel A channel.
     * @return float The SPIM NA.
     */
    private function getSpimNA($channel)
    {
        $microSetting = $this->microSetting;
        $spimNA = $microSetting->parameter("SpimNA")->value();
        return $spimNA[$channel];
    }

    /**
     * Gets the SPIM Fill Factor. One channel.
     * @param int $channel A channel
     * @return float The SPIM Fill Factor.
     */
    private function getSpimFill($channel)
    {
        $microSetting = $this->microSetting;
        $spimFill = $microSetting->parameter("SpimFill")->value();
        return $spimFill[$channel];
    }

    /**
     * Gets the SPIM Direction. One channel.
     * @param int $channel A channel
     * @return float The SPIM Direction.
     */
    private function getSpimDir($channel)
    {
        $microSetting = $this->microSetting;
        $spimDir = $microSetting->parameter("SpimDir")->value();
        $direction = $spimDir[$channel];
        $angle = 0.0;

        switch ($direction) {
            case '':
            case 'From right':
                $angle = 0.0;
                break;
            case 'From left':
                $angle = 180.0;
                break;
            case 'From top':
                $angle = 90.0;
                break;
            case 'From bottom':
                $angle = 270.0;
                break;
            case 'Right + left':
                $angle = 0.0;
                break;
            case 'Top + bottom':
                $angle = 90.0;
                break;
            default:
                Log::error("Unknown SPIM direction: $direction");
        }

        return $angle;
    }

    /**
     * Gets the STED depletion mode. One channel.
     * @param int $channel A channel
     * @param bool $parseConfocals True if the major microscope mode is confocal,
     * false otherwise.
     * @return string The STED depletion mode.
     */
    private function getStedMode($channel, $parseConfocals = False)
    {
        $microSetting = $this->microSetting;
        $stedMode = $microSetting->parameter("StedDepletionMode")->value();
        $deplMode = $stedMode[$channel];

        /* In this case the major microscope mode will be confocal, and the
           STED depletion mode will not matter. */
        if ($parseConfocals) {
            if (strstr($deplMode, 'confocal')) {
                $deplMode = "vortexPulsed";
            }
        }

        return $deplMode;
    }

    /**
     * Gets the STED lambda. One channel.
     * @param int $channel A channel
     * @return int The STED lambda.
     */
    private function getStedLambda($channel)
    {
        $microSetting = $this->microSetting;
        $stedLambda = $microSetting->parameter("StedWavelength")->value();
        return $stedLambda[$channel];
    }

    /**
     * Gets the STED saturation factor. One channel.
     * @param int $channel A channel
     * @return float The STED saturation factor.
     */
    private function getStedSaturationFactor($channel)
    {
        $microSetting = $this->microSetting;
        $stedSatFact =
            $microSetting->parameter("StedSaturationFactor")->value();
        return $stedSatFact[$channel];
    }

    /**
     * Gets the STED immunity factor. One channel.
     * @param int $channel A channel
     * @return float The STED immunity factor.
     */
    private function getStedImmunity($channel)
    {
        $microSetting = $this->microSetting;
        $stedImmunity = $microSetting->parameter("StedImmunity")->value();
        return $stedImmunity[$channel];
    }

    /**
     * Gets the STED lambda. One channel.
     * @param int $channel A channel
     * @return int The STED lambda.
     */
    private function getSted3D($channel)
    {
        $microSetting = $this->microSetting;
        $sted3D = $microSetting->parameter("Sted3D")->value();
        return $sted3D[$channel];
    }

    /**
     * Gets the pinhole radius. One channel.
     * @param int $channel A channel
     * @return float The pinhole radius.
     */
    private function getPinholeRadius($channel)
    {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize")->value();
        return $pinholeSize[$channel];
    }

    /**
     * Gets the microscope type.
     * @param int $channel A channel
     * @return string The microscope type.
     */
    private function getMicroscopeType($channel)
    {
        $microChoice = $this->microSetting->parameter('MicroscopeType');
        $micrType = $microChoice->translatedValue();

        if (strstr($micrType, 'sted3d')) {
            $micrType = 'sted';
        }

        if (strstr($micrType, 'sted')) {
            $stedMode = $this->getStedMode($channel);

            if (strstr($stedMode, 'confocal')) {
                $micrType = 'confocal';
            }
        }

        return $micrType;
    }

    /**
     * Gets the objective's refractive index. Same for all channels.
     * @return float The objective's refractive index.
     */
    private function getLensRefractiveIndex()
    {
        $microSetting = $this->microSetting;
        return $microSetting->parameter('ObjectiveType')->translatedValue();
    }

    /**
     * Gets the numerical aperture. Same for all channels.
     * @return float The numerical aperture.
     */
    private function getNumericalAperture()
    {
        $microSetting = $this->microSetting;
        return $microSetting->parameter('NumericalAperture')->value();
    }

    /**
     * Gets the pinhole spacing. One channel.
     * @param int $channel A channel
     * @return float The pinhole spacing.
     */
    private function getPinholeSpacing($channel)
    {
        if ($this->getParameterValue("micr", $channel) != "nipkow") {
            return "";
        }

        $microSetting = $this->microSetting;
        return $microSetting->parameter("PinholeSpacing")->value();
    }

    /**
     * Gets the Objective Quality. Same for all channels.
     * @return string The objective quality.
     * @todo To be implemented in the GUI
     */
    private function getObjectiveQuality()
    {
        return $this->setpArray['objQuality'];
    }

    /**
     * Gets the excitation photon count. Same for all channels.
     * @return int The excitation photon count.
     */
    private function getExcitationPhotonCount()
    {
        if ($this->microSetting->isTwoPhoton()) {
            return 2;
        } else {
            return 1;
        }
    }

    /**
     * Gets the excitation wavelength. One channel.
     * @param int $channel A channel
     * @return int The excitation wavelength.
     */
    private function getExcitationWavelength($channel)
    {
        $microSetting = $this->microSetting;
        $excitationLambdas = $microSetting->parameter("ExcitationWavelength");
        $excitationLambda = $excitationLambdas->value();
        return $excitationLambda[$channel];
    }

    /**
     * Gets the excitation beam overfill factor. Same for all channels.
     * @return float The excitation beam factor.
     */
    private function getExcitationBeamFactor()
    {
        return $this->setpArray['exBeamFill'];
    }

    /**
     * Gets the emission wavelength. One channel.
     * @param int $channel A channel
     * @return int The emission wavelength.
     */
    private function getEmissionWavelength($channel)
    {
        $microSetting = $this->microSetting;
        $emissionLambdas = $microSetting->parameter("EmissionWavelength");
        $emissionLambda = $emissionLambdas->value();
        return $emissionLambda[$channel];
    }

    /**
     * Gets the imaging direction. Same for all channels.
     * @return string Whether the imaging was carried out 'downwards' or 'upwards'.
     */
    private function getImagingDirection()
    {
        $microSetting = $this->microSetting;
        $coverslip = $microSetting->parameter('CoverslipRelativePosition');
        $coverslipPos = $coverslip->value();
        if ($coverslipPos == 'farthest') {
            return "downward";
        } else {
            return "upward";
        }
    }

    /**
     * Gets the medium refractive index. Same for all channels.
     * @return float The medium's refractive index.
     */
    private function getMediumRefractiveIndex()
    {
        $microSetting = $this->microSetting;
        $sampleMedium = $microSetting->parameter("SampleMedium");
        return $sampleMedium->translatedValue();
    }

    /**
     * Gets the sampling distance in the X direction.
     * @return string|int The sampling in the X direction, if specified by
     * the user.
     */
    public function getSamplingSizeX()
    {
        if ($this->microSetting->sampleSizeX() != 0) {
            return $this->microSetting->sampleSizeX();
        } else {
            return "*";
        }
    }

    /**
     * Gets the sampling distance in the Y direction.
     * @return string|int The sampling in the Y direction, if specified by
     * the user.
     */
    public function getSamplingSizeY()
    {
        if ($this->microSetting->sampleSizeY() != 0) {
            return $this->microSetting->sampleSizeY();
        } else {
            return "*";
        }
    }

    /**
     * Gets the sampling distance in the Z direction.
     * @return string|int The sampling in the Z direction, if specified by
     * the user.
     */
    public function getSamplingSizeZ()
    {
        if ($this->microSetting->sampleSizeZ() != 0) {
            return $this->microSetting->sampleSizeZ();
        } else {
            return "*";
        }
    }

    /**
     * Gets the sampling interval in T.
     * @return string|float The sampling in T, if specified by the user.
     */
    public function getSamplingSizeT()
    {
        /* The T sampling may be 0 when dealing with 3D data sets. */
        return $this->microSetting->sampleSizeT();
    }

    /**
     * Gets the sampling size of the 'raw' image.
     * @return string Tcl-list with the sampling sizes.
     */
    private function getSamplingSizes()
    {
        $sampling = $this->getSamplingSizeX();
        $sampling .= " " . $this->getSamplingSizeY();
        $sampling .= " " . $this->getSamplingSizeZ();
        $sampling .= " " . $this->getSamplingSizeT();

        return $this->string2tcllist($sampling);
    }

    /* -------------------------- Algorithm task ----------------------------- */

    /**
     * Gets options for the 'algorithm' task. One channel.
     * @param int $channel A channel.
     * @return string Tcl list with the deconvolution 'algorithm' task + its
     * options.
     */
    private function getTaskDescrAlgorithm($channel)
    {

        $taskDescr = "";

        foreach ($this->algArray as $key => $value) {

            if ($key != "mode" && $key != "reduceMode" 
                && $key != "itMode" && $key != 'listID') {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'timeOut':
                case 'pad':
                    $taskDescr .= $value;
                    break;
                case 'blMode':
                    $taskDescr .= $this->getBleachingMode();
                    break;
                case 'q':
                    $taskDescr .= $this->getQualityFactor($channel);
                    break;
                case 'brMode':
                    $taskDescr .= $this->getBrMode();
                    break;
                case 'varPsf':
                    $taskDescr .= $this->getVarPsf($channel);
                    break;
                case 'it':
                    $taskDescr .= $this->getIterations($channel);
                    break;
                case 'bgMode':
                    $taskDescr .= $this->getBgMode();
                    break;
                case 'bg':
                    $taskDescr .= $this->getBgValue($channel);
                    break;
                case 'snr':
                    $taskDescr .= $this->getSnrValue($channel);
                    break;
                case 'acuity':
                    $taskDescr .= $this->getAcuityValue($channel);
                    break;
                case 'acuityMode':
                    $taskDescr .= $this->getAcuityMode();
                    break;
                case 'psfMode':
                    $taskDescr .= $this->getPsfMode();
                    break;
                case 'psfPath':
                    $taskDescr .= $this->getPsfPath($channel);
                    break;
                case 'mode':
                    if ($this->getAlgorithm($channel) == "cmle") {
                        $taskDescr .= " " . $key . " ";
                        $taskDescr .= $value;
                    }
                    break;
                case 'itMode':
                    if ($this->getAlgorithm($channel) == "qmle") {
                        $taskDescr .= " " . $key . " ";
                        $taskDescr .= $value;
                    }
                    break;
                case 'reduceMode':
                    if ($this->getAlgorithm($channel) == "cmle") {
                        $taskDescr .= " " . $key . " ";
                        $taskDescr .= $this->getArrDetReductionMode();                        
                    }                    
                    break;                
                case 'listID':
                    break;
                default:
                    Log::error("Deconvolution option $key not yet implemented");
            }
        }

        return $this->string2tcllist($taskDescr);
    }

    /**
     * Gets the brick mode.
     * @return string Brick mode.
     */
    private function getBrMode()
    {
        $SAcorr = $this->getSAcorr();

        $brMode = "auto";

        if ($SAcorr['AberrationCorrectionNecessary'] == 1) {
            if ($SAcorr['AberrationCorrectionMode'] != 'automatic') {
                if ($SAcorr['AdvancedCorrectionOptions'] == 'slice') {
                    $brMode = 'sliceBySlice';
                } elseif ($SAcorr['AdvancedCorrectionOptions'] == 'few') {
                    $brMode = 'few';
                } elseif ($SAcorr['AdvancedCorrectionOptions'] == 'few-slabs') {
                    $brMode = 'one';
                }
            }
        }

        return $brMode;
    }

    /**
     * Gets the varPsf mode.
     * @param int $channel A channel
     * @return string varPsf mode.
     */
    private function getVarPsf($channel)
    {
        $SAcorr = $this->getSAcorr();

        $varPsf = "off";

        if ($SAcorr['AberrationCorrectionNecessary'] == 1) {
            if ($SAcorr['AberrationCorrectionMode'] != 'automatic') {
                if ($SAcorr['AdvancedCorrectionOptions'] == 'few-slabs') {
                    $varPsf = 'few';
                }
            }
        }

          /* Special case for GMLE where varPsf is always on. */
        if ($this->getAlgorithm($channel) == "gmle" && $varPsf == "off") {
            $varPsf = "one";
        }

        return $varPsf;
    }

    /**
     * Gets the array detector reduction mode.
     * @return string Reduction mode.
     */
    private function getArrDetReductionMode()
    {
        /* Initialize. */
        $reductionModeStr = "auto";

        $reductionModeParam = $this->deconSetting->parameter("ArrayDetectorReductionMode");
        $reductionModeValue = $reductionModeParam->value();

        switch ($reductionModeValue) {
            case 'auto':
            case 'all':
            case 'safe':
            case 'no':
            case 'superY':
            case 'superXY':
                $reductionModeStr = $reductionModeValue;
                break;
            case 'core no':
                $reductionModeStr = "coreNo";
                break;
            case 'core all':
                $reductionModeStr = "coreAll";
                break;
            case 'aggressive':
                $reductionModeStr = "aggr";
                break;            
            default:
                Log::error("Reduction mode '$reductionModeValue' not yet implemented.");                
        }

        return $reductionModeStr;
    }

    /**
     * Gets the background mode.
     * @return string Background mode.
     */
    private function getBgMode()
    {
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

    /**
     * Gets the background value. One channel.
     * @param int $channel A channel
     * @return string|float The background value.
     * @todo Even in case of error, the function should return something usable!
     */
    private function getBgValue($channel)
    {
        if ($this->getBgMode() == "auto" || $this->getBgMode() == "object") {
            return 0.0;
        } elseif ($this->getBgMode() == "manual") {
            $deconSetting = $this->deconSetting;
            $bgRate =
                $deconSetting->parameter("BackgroundOffsetPercent")->value();
            return $bgRate[$channel];
        } else {
            // @todo Return something usable!
            Log::error("Unknown background mode for channel $channel.");
            return;
        }
    }

    /**
     * Gets the bleaching mode.
     * @return string Bleaching mode.
     */
    private function getBleachingMode()
    {
        /** @var TaskSetting $deconSetting */
        $deconSetting = $this->deconSetting;

        $blMode = $deconSetting->parameter("BleachingMode")->value();
        
        return $blMode;
    }
    
    /**
     * Gets the SNR value. One channel.
     * @param int $channel A channel
     * @return int|string The SNR value.
     */
    private function getSnrValue($channel)
    {
        /** @var TaskSetting $deconSetting */
        $deconSetting = $this->deconSetting;
        $snrRate = $deconSetting->parameter("SignalNoiseRatio")->value();
        $snrValue = $snrRate[$channel];

        return $snrValue;
    }

    /**
     * Gets the acuity value. One channel.
     * @param int $channel A channel
     * @return int|string The acuity value.
     */
    private function getAcuityValue($channel)
    {
        /** @var TaskSetting $deconSetting */
        $deconSetting = $this->deconSetting;
        $acuityRate = $deconSetting->parameter("Acuity")->value();
        $acuityValue = $acuityRate[$channel];
	    
	if ($acuityValue == "") {
	    $acuityValue = 0;
	}

        return $acuityValue;
    }

    /**
     * Gets the acuity mode.
     * @return string Acuity mode.
     */
    private function getAcuityMode()
    {
        $deconSetting = $this->deconSetting;
        $acuityMode = $deconSetting->parameter("AcuityMode")->value();
        
        return $acuityMode;
    }

    /**
     * Gets the PSF mode.
     * @return string PSF mode.
     */
    private function getPsfMode()
    {
        $microSetting = $this->microSetting;
        $psfMode = $microSetting->parameter("PointSpreadFunction")->value();
        if ($psfMode == "theoretical") {
            return "auto";
        } else {
            return "file";
        }
    }

    /**
     * Gets the PSF path including subfolders created by the user.
     * @param int $channel A channel
     * @return string Psf path
     */
    private function getPsfPath($channel)
    {
        if ($this->getPsfMode() == "file") {

            /* The PSF may be located in a different subfolder than the raw data.
             Thus, its path must be found independently of the raw images. */
            $userFileArea = $this->jobDescription->sourceFolder();
            $microSetting = $this->microSetting;
            $psfFiles = $microSetting->parameter("PSF")->value();
            $psfPath = trim($userFileArea . "/" . $psfFiles[$channel]);
        } else {
            $psfPath = "";
        }

        return $this->string2tcllist($psfPath);
    }

    /**
     * Gets the deconvolution quality factor.
     * @return float The quality factor
     */
    private function getQualityFactor($channel)
    {
        $deconSetting = $this->deconSetting;
        $q = $deconSetting->parameter('QualityChangeStoppingCriterion')->value();
        return $q[$channel];
    }

    /**
     * Gets the maximum number of iterations for the deconvolution.
     * @return int The maximum number of iterations.
     */
    private function getIterations($channel)
    {
        $deconSetting = $this->deconSetting;
        $it = $deconSetting->parameter("NumberOfIterations")->value();
        return $it[$channel];
    }

    /**
     * Gets the deconvolution algorithm for each channel.
     * @param int $chan Channel number.
     * @todo Use channel index.
     * @return string Deconvolution algorithm.
     */
    private function getAlgorithm($chan = -1)
    {   
        $deconAlg = "";

        $settingAlg = $this->deconSetting->parameter('DeconvolutionAlgorithm')->value();        
        foreach ($this->deconArray as $key => $value) {
            if ($settingAlg[$chan] == $key) {
                $deconAlg = $value;
                break;
            }            
        }        
        
        return $deconAlg; 
    }

    /**
     * Gets the spherical aberration correction.
     * @return string Sperical aberration correction.
     */
    private function getSAcorr()
    {
        return $this->microSetting->getAberractionCorrectionParameters();
    }

    /* -------------- Chromatic Aberration correction tasks ------------------ */

    /**
     * Creates an array with the target channels.
     * @return array The target array.
     */
    private function getChansForChromaticCorrection()
    {
        $channelsArray = array();

        $chanCnt = $this->getChanCnt();
        if ($chanCnt < 2) {
            return $channelsArray;
        }

        
        for ($chan = 0; $chan < $chanCnt; $chan++) {
            /** @var ChromaticAberrationCh$chan $chromaticParamCh$chan */
            $chromaticParam =
                $this->deconSetting->parameter("ChromaticAberrationCh" . $chan);
            $chromaticChan = $chromaticParam->value();

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

    /**
     * Gets options for the 'colocalization' task.
     * @param int $chanR A channel number acting as red channel.
     * @param int $chanG A channel number acting as green channel.
     * @param int $runCnt The number of colocalization tasks
     * @return string Tcl list with the 'colocalization' task and its options.
     */
    private function getTaskDescrColoc($chanR, $chanG, $runCnt)
    {

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

            switch ($key) {
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
                    $coefficients = $this->getColocCoefficients();
                    $taskDescr .= $this->string2tcllist($coefficients);
                    break;
                case 'map':
                    if ($this->getColocMap() == "") {
                        $taskDescr .= "none";
                    } else {
                        $taskDescr .= $this->getColocMap();
                    }
                    break;
                case 'destDir':
                    $destDir = $this->getDestDir() . "/hrm_previews";
                    $taskDescr .= $this->string2tcllist($destDir);
                    break;
                case 'destFile':
                    $destFile = $this->getThumbBaseName() . ".";
                    $destFile .= $this->getColocMap() . ".map_chan";
                    $destFile .= $chanR . "_" . "chan" . $chanG;
                    $taskDescr .= $destFile;
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $taskDescr = $value . ":" . $runCnt . " " . $taskDescr . " ";
                    break;
                default:
                    Log::error("Colocalization option '$key' not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /**
     * Gets the value of the boolean choice 'Colocalization Analysis'.
     * @return bool Whether or not colocalization analysis should be performed.
     */
    private function getColocalization()
    {
        return $this->analysisSetting->parameter('ColocAnalysis')->value();
    }

    /**
     * Gets the channel choice for the colocalization analysis.
     * @return array Which channels to use in the colocalization analysis.
     */
    private function getColocChannels()
    {
        $colocChannels = $this->analysisSetting->parameter('ColocChannel')->value();

        /* Do not count empty elements. Do count channel '0'. */
        return array_filter($colocChannels, 'strlen');
    }

    /**
     * Gets the value of the choice 'Colocalization Coefficients'.
     * @return string Which colocalization coefficients should be calculated.
     */
    private function getColocCoefficients()
    {
        $colocCoefficients = "";
        $coefArr = $this->analysisSetting->parameter('ColocCoefficient')->value();

        foreach ($coefArr as $key => $coefficient) {
            $colocCoefficients .= $coefficient . " ";
        }

        return $colocCoefficients;
    }

    /**
     * Gets the value of the choice 'Colocalization Map'.
     * @return string Which colocalization map should be created.
     */
    private function getColocMap()
    {
        return $this->analysisSetting->parameter('ColocMap')->value();
    }

    /**
     * Gets the colocalization threshold mode.
     * @return string The colocalization threshold mode.
     */
    private function getColocThreshMode()
    {
        $bgParam = $this->analysisSetting->parameter("ColocThreshold");
        $bgValue = $bgParam->value();

        if ($bgValue[0] == "auto") {
            return "auto";
        } else {
            return "manual";
        }
    }

    /**
     * Gets the coloc background (threshold) value. One channel.
     * @param int $channel A channel
     * @return int The colocalization background (threshold) value.
     * @todo Even in case of error, this method should return something!
     */
    private function getColocThreshValue($channel)
    {
        if ($this->getColocThreshMode() == "auto") {
            return 0.0;
        } elseif ($this->getColocThreshMode() == "manual") {
            $analysisSetting = $this->analysisSetting;
            $bgRate = $analysisSetting->parameter("ColocThreshold")->value();
            return $bgRate[$channel];
        } else {
            Log::error("Unknown colocalization threshold mode.");
            // @TODO Return something usable!
            return;
        }
    }


    /* --------------------- Histogram tasks ---------------------------- */

    /**
     * Gets options for the 'histogram' task.
     * @param int $chanR A channel number acting as red channel.
     * @param int $chanG A channel number acting as green channel.
     * @param int $runCnt The number of histogram tasks
     * @return string Tcl list with the 'histogram' task and its options.
     */
    private function getTaskDescrHistogram($chanR, $chanG, $runCnt)
    {

        $taskDescr = "";

        foreach ($this->histoArray as $key => $value) {

            if ($key != "listID") {
                $taskDescr .= " " . $key . " ";
            }

            switch ($key) {
                case 'chanR':
                    $taskDescr .= $chanR;
                    break;
                case 'chanG':
                    $taskDescr .= $chanG;
                    break;
                case 'destDir':
                    $destDir = $this->getDestDir() . "/hrm_previews";
                    $taskDescr .= $this->string2tcllist($destDir);
                    break;
                case 'destFile':
                    $destFile = $this->getThumbBaseName() . ".hist_chan" . $chanR;
                    $destFile .= "_" . "chan" . $chanG;
                    $taskDescr .= $destFile;
                    break;
                case 'listID':
                    $taskDescr = $this->string2tcllist($taskDescr);
                    $taskDescr = $value . ":" . $runCnt . " " . $taskDescr . " ";
                    break;
                default:
                    Log::error("2D histogram option '$key' not yet implemented.");
            }
        }

        return $taskDescr;
    }

    /* ------------------------ Thumbnail tasks------------------------------- */

    /**
     * Gets options for each of the thumbnail tasks.
     * @param string $taskKey A thumbnail type of task
     * @param int $thumbID The number of this thumbnail task.
     * @return string Tcl-compliant list with the thumbnail generation details.
     */
    private function getThumbnailTaskDescr($taskKey, $thumbID)
    {

        /* Get the Huygens task name of the thumbnail task */
        $task = $this->getTaskName($taskKey, $thumbID);
        if ($task == "") {
            // @todo Return something usable!
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
            switch ($key) {
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
                    Log::error("Thumb preview option $key not yet implemented");
            }
        }

        $taskDescr = $this->string2tcllist($taskDescr);
        $taskDescr = " " . $task . " " . $taskDescr;

        return $taskDescr;
    }

    /**
     * Gets the destination file name of the thumbnail.
     * @return string A name for the thumbnail file.
     */
    private function getThumbnailName()
    {

        $destFile = $this->getThumbBaseName();

        if ($this->thumbFrom == "raw") {
            switch ($this->thumbType) {
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
                    Log::error("Unknown thumbnail type");
            }
        }

        if ($this->thumbFrom == "deconvolved") {
            switch ($this->thumbType) {
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
                    Log::error("Unknown thumbnail type");
            }
        }

        if (isset($suffix)) {
            $destFile .= $suffix;
        }
        $destFile = $this->string2tcllist($destFile);

        return $destFile;
    }

    /**
     * Gets the subimage name between parenthesis as suffix
     * @param  string $subImg Whether or not a sub image is being dealt with
     * @return string The image suffix
     */
    private function getSubImageSuffix($subImg)
    {
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

    /**
     * Gets the environment export format option.
     * @return string Tcl-complaint nested list with the export format details
     */
    private function getExportFormat()
    {

        $exportFormat = "";
        foreach ($this->expFormatArray as $key => $value) {
            $exportFormat .= " " . $key . " ";

            switch ($key) {
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
                    Log::error("Export format option $key not yet implemented");
            }
        }

        return $this->string2tcllist($exportFormat);
    }

    /**
     * Gets the basic name that all thumbnails share, based on job id.
     * @return string The thumbnail basic name.
     */
    private function getThumbBaseName()
    {
        $basename = basename($this->destImage);
        $filePattern = "/(.*)_(.*)_(.*)\.(.*)$/";
        preg_match($filePattern, $basename, $matches);
        return $matches[2] . "_" . $matches[3] . "." . $matches[4];
    }

    /**
     * Gets the parameter confidence level for all channels.
     * @param string $paramName The parameter name.
     * @return string The confidence level.
     */
    private function getParameterConfidence($paramName)
    {
        switch ($paramName) {
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
            case 'spimExcMode':
            case 'spimGaussWidth':
            case 'spimCenterOffset':
            case 'spimFocusOffset':
            case 'spimNA':
            case 'spimFill':
            case 'spimDir':
                $chanCnt = $this->getChanCnt();
                $cList = "";

                for ($chan = 0; $chan < $chanCnt; $chan++) {
                    $cLevel = $this->getConfidenceLevel($paramName, $chan);
                    $cList .= "  " . $cLevel;
                }
                $paramConf = $this->string2tcllist($cList);
                break;
            case 's':
                $cList = $this->getConfidenceLevel("sX", 0) . " ";
                $cList .= $this->getConfidenceLevel("sX", 0) . " ";
                $cList .= $this->getConfidenceLevel("sZ", 0) . " ";
                $cList .= $this->getConfidenceLevel("sT", 0);
                $paramConf = $this->string2tcllist($cList);
                break;
            case 'iFacePrim':
            case 'iFaceScnd':
                $paramConf = $this->getConfidenceLevel($paramName, 0);
                break;
            default:
                // @todo Return something usable in this case!
                Log::error("Parameter $paramName not yet implemented");

                // @TODO Set $paramConf to something usable!
        }

        return $paramConf;
    }

    /**
     * Gets the confidence level of one parameter and one channel.
     * @param string $parameter The parameter name
     * @param int $channel The channel number
     * @return string 'noMetaData' if the user entered a value for the parameter,
     * 'default' otherwise.
     */
    private function getConfidenceLevel($parameter, $channel)
    {

        /* Parameter not set by the user, should be read from the metadata. */
        $parameterValue = $this->getParameterValue($parameter, $channel);
        if (strpos($parameterValue, '*') !== FALSE) {
            return "default";
        }

        /* Parameters initialized with a value in setpArray do not exist
         in HRM yet. We should try to read them from the metadata. */
        if (array_key_exists($parameter, $this->setpArray)) {
            if ($this->setpArray[$parameter] != '') {
                return "default";
            }
        }

        /* Parameter set by the user. */
        return "noMetaData";
    }

    /**
     * Gets the value and confidence level of specific job parameters.
     * @param string $paramName The name of the parameter.
     * @param string $default A default value for the parameter.
     * @return string A Tcl-compliant list with values and confidence levels.
     */
    private function getParameter($paramName, $default = null)
    {

        /* Start by adding the parameter name */
        $paramList = " " . $paramName . " ";

        /* Then add the parameter value */
        switch ($paramName) {
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
            case 'spimExcMode':
            case 'spimGaussWidth':
            case 'spimCenterOffset':
            case 'spimFocusOffset':
            case 'spimNA':
            case 'spimFill':
            case 'spimDir':
                $chanCnt = $this->getChanCnt();
                $param = "";
                for ($chan = 0; $chan < $chanCnt; $chan++) {
                    if (!$default) {
                        $param .= $this->getParameterValue($paramName, $chan);
                        $param .= " ";
                    } else {
                        $param .= $default . " ";
                    }
                }
                $paramList .= $this->string2tcllist($param);
                break;
            default:
                Log::error("Multichannel parameter $paramName not yet implemented");
        }

        /* Then add the parameter confidence level */
        $paramList .= " " . $this->setpConfArray[$paramName] . " ";
        $paramList .= $this->getParameterConfidence($paramName);

        return $paramList;
    }

    /**
     * Gets the value of specific parameters.
     * @param string $paramName The parameter name
     * @param int $channel A channel number
     * @return string|int|float The parameter value when existing, '*' otherwise.
     */
    private function getParameterValue($paramName, $channel)
    {
        switch ($paramName) {
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
            case 'spimExcMode':
                $parameterValue = $this->getSpimExcMode($channel);
                break;
            case 'spimGaussWidth':
                $parameterValue = $this->getSpimGaussWidth($channel);
                break;
            case 'spimCenterOffset':
                $parameterValue = $this->getSpimCenterOffset($channel);
                break;
            case 'spimFocusOffset':
                $parameterValue = $this->getSpimFocusOffset($channel);
                break;
            case 'spimNA':
                $parameterValue = $this->getSpimNA($channel);
                break;
            case 'spimFill':
                $parameterValue = $this->getSpimFill($channel);
                break;
            case 'spimDir':
                $parameterValue = $this->getSpimDir($channel);
                break;
            default:
                $parameterValue = "";
        }

        if ($parameterValue !== 0
            && ($parameterValue == "" || $parameterValue == "{}")
        ) {
            $parameterValue = "*";
        }

        return $parameterValue;
    }

    /**
     * Gets the Huygens task name of a task.
     *
     * Notice that integers often need to be appended to the names.
     *
     * @param string $key A task array key
     * @param string $task A task compliant with the Huygens template task names
     * @return string The task name (includes channel number, preview number, etc.)
     */
    private function getTaskName($key, $task)
    {
        switch ($task) {
            case 'imgOpen':
            case 'setp':
            case 'autocrop':
            case 'adjbl':
            case 'imgSave':
            case 'stabilize':
            case 'stabilize:post':   
                break;
            case 'coloc':
            case 'hist':
                $task = $this->getNameTaskMultiChan($task);
                break;
            case 'previewGen':
                $task = $this->getNameTaskPreviewGen($key, $task);
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
                Log::error("Huygens template task '$task' not yet implemented.");
        }

        return $task;
    }

    /**
     * Gets the Huygens name for a chromatic task.
     * @todo Document this input argument!
     * @param ?? $task ??
     * @return string Task name.
     * @todo Fix the documentation of this method!
     */
    private function getNameTaskChromatic($task)
    {

        $chromaticTasks = "";

        $channelsArray = $this->getChansForChromaticCorrection();

        if (empty($channelsArray)) {
            return $chromaticTasks;
        }

        foreach ($channelsArray as $chanKey => $chan) {
            $chromaticTasks .= $task . ":$chanKey ";
        }

        return trim($chromaticTasks);
    }

    /**
     * Gets the Huygens deconvolution task names of every channel
     * @return string The Huygens deconvolution task names
     */
    private function getNameTaskAlgorithm()
    {
        $chanCnt = $this->getChanCnt();
        $algorithms = "";
        for ($chan = 0; $chan < $chanCnt; $chan++) {
            $algorithms .= $this->getAlgorithm($chan) . ":$chan ";
        }
        return trim($algorithms);
    }

    /**
     * Gets the Huygens task name of a task run every two channels.
     * @param string $task A task array key
     * @return string The Huygens task name.
     */
    private function getNameTaskMultiChan($task)
    {

        $tasks = "";

        /* At the moment there are only coloc/hist tasks as multichannel task.
         There must be as many runs as specified by the coloc channels. */
        $runCnt = $this->getNumberColocRuns();

        if ($runCnt == 0) return $tasks;

        /* The template task run counter starts at 0. */
        for ($run = 0; $run < $runCnt; $run++) {
            $tasks .= $task . ":$run ";
        }

        return trim($tasks);
    }

    /**
     * Gets the Huygens task name of a thumbnail task
     * @param string $key A task array key
     * @param string $task A task compliant with the Huygens template task names
     * @return string The Huygens preview task name
     */
    private function getNameTaskPreviewGen($key, $task)
    {
        global $useThumbnails;
        global $saveSfpPreviews;
        global $movieMaxSize;

        if (!$useThumbnails) {
            // @todo Return something usable!
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

    /**
     * Gets the number of colocalization runs as a result of combining
     * the choice of channels for colocalization analysis.
     * @return int The number of colocalization runs.
     */
    private function getNumberColocRuns()
    {

        $colocRuns = 0;

        if ($this->getColocalization() == "1") {

            $chanCnt = count($this->getColocChannels());

            /* The number of combinations between channels obeys the following
             formula: n ( n - 1 ) / 2 ; where n is the number of channels. */
            $colocRuns = $chanCnt * ($chanCnt - 1) / 2;
        }

        return $colocRuns;
    }

    /**
     * Checks whether a hot pixel correction mask exists.
     * @param void
     * @return bool true if it exists false otherwise.
     */
    private function hotPixelMaskExists()
    {
        $userFileArea = $this->jobDescription->sourceFolder();
        $deconSetting = $this->deconSetting;
        $hpcFile = $deconSetting->parameter("HotPixelCorrection")->value();

        if ($hpcFile[0] == "") {
	    return false;
        } else {
            return true;
        }
    }

    /**
     * Checks whether a JPEG can be made from the image.
     * @param string $image The image to be checked.
     * @return void
     */
    private function isEligibleForSlicers($image)
    {
        global $maxComparisonSize;

        $this->compareZviews = TRUE;
        $this->compareTviews = TRUE;

        if ($maxComparisonSize == 0) {
            $this->compareZviews = FALSE;
            $this->compareTviews = FALSE;
            return;
        }

        /* The dimensions of the raw and restored data will be different
           if the time stabilization is on with a cropping scheme other than
           'original'. In that case disable the T comparison. */
        $TStabilizeParam = $this->deconSetting->parameter('TStabilization');
        $TStabilizeCroppingParam = $this->deconSetting->parameter('TStabilizationCropping');

        if ($TStabilizeParam->value() && $TStabilizeCroppingParam->value() != "original") {
            $this->compareTviews = FALSE;
        }        
    }

    /**
     * Gets the open image series mode for time series.
     * @return string The series mode: whether auto or off.
     */
    private function getSeriesMode()
    {

        if ($this->jobDescription->autoseries()) {
            $seriesMode = "auto";
        } else {
            $seriesMode = "off";
        }

        return $seriesMode;
    }

    /**
     * Whether or not an image has sub images.
     * @return bool True if the image has sub images, false otherwise.
     */
    private function hasSubImage()
    {
        if (isset($this->subImage)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the current date in format: Wed Feb 02 16:02:11 CET 2011
     * @return string The date
     */
    private function getTemplateDate()
    {
        $today = date("D M j G:i:s T Y");
        $today = $this->string2tcllist($today);
        return $today;
    }

    /**
     * Gets the template name as batch_2011-02-02_16-02-08
     * @return string The template name.
     */
    private function getTemplateName()
    {
        $time = date('h-i-s');
        $today = date('Y-m-d');
        $templateName = "batch_" . $today . "_" . $time;
        return $templateName;
    }

    /**
     * Gets value for the multidir export format option.
     * @return bool A boolean with the multidir option value.
     */
    private function getMultiDirOpt()
    {
        $outputType = $this->getOutputFileType();

        if (preg_match("/tif/i", $outputType)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Gets the number of channels selected by the user.
     * @return int Number of channels.
     */
    private function getChanCnt()
    {
        return $this->microSetting->numberOfChannels();
    }

    /**
     * Wraps a string between curly braces to turn it into a Tcl list.
     * @param string $string A string
     * @return string A Tcl list.
     */
    private function string2tcllist($string)
    {
        $tcllist = trim($string);
        $tcllist = "{{$tcllist}}";
        return $tcllist;
    }

    /**
     * Removes the wrapping of a Tcl list to leave just a string.
     * @param string $tcllist A string acting as Tcl list
     * @return string A string.
     */
    private function tcllist2string($tcllist)
    {
        $string = str_replace("{", "", $tcllist);
        $string = str_replace("}", "", $string);
        return $string;
    }

    /**
     * Sets the name of the source image with subimages, if any.
     *
     * @todo This method seems to dynamically create the $this->subImage field. Check!
     */
    private function setSrcImage()
    {
        $this->srcImage = $this->jobDescription->sourceImageName();

        /*If a (string) comes after the file name, the string is interpreted
         as a subimage. Currently this is for LIF, LOF and CZI files only. */
        if (preg_match("/^(.*\.(lif|czi|lof|nd|msr|obf))\s\((.*)\)/i",
            $this->srcImage, $match)) {
            $this->srcImage = $match[1];
            $this->subImage = $match[3]; // @todo Is this correct?
        }
    }

    /**
     * Gets the directory of the source image.
     * @return string A file path.
     */
    private function getSrcDir()
    {
        return dirname($this->srcImage);
    }

    /**
     * Sets the name of the destination image.
     */
    private function setDestImage()
    {
        $this->destImage = $this->jobDescription->destinationImageFullName();
        $fileType = $this->getOutputFileExtension();
        $this->destImage = $this->destImage . "." . $fileType;
    }

    /**
     * Gets a basic name for the deconvolved image.
     * @return string The image's basic name.
     */
    private function getDestImageBaseName()
    {
        $destInfo = pathinfo($this->destImage);
        return basename($this->destImage, '.' . $destInfo['extension']);
    }

    /**
     * Gets the directory of the destination image.
     * @return string A file path.
     */
    private function getDestDir()
    {
        return dirname($this->destImage);
    }

    /**
     * Gets the file type of the destination image.
     * @return string A file type: whether imaris, ome, etc.
     */
    private function getOutputFileType()
    {
        /** @var OutputFileFormat $outFileFormat */
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->translatedValue();
    }

    /**
     * Gets the file extension of the destination image.
     * @return string A file extension: whether ims, tif, etc.
     */
    private function getOutputFileExtension()
    {
        /** @var OutputFileFormat $outFileFormat */
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->extension();
    }

    /* ----------------------------------------------------------------------- */
}
