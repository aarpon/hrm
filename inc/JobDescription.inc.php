<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Parameter.inc.php");
require_once("Database.inc.php");
require_once("User.inc.php");
require_once("Shell.inc.php");
require_once("hrm_config.inc.php");
require_once("Job.inc.php");
require_once("JobQueue.inc.php");

/*!
  \class  JobDescription
  \brief  Collects all information for a deconvolution Job to be created

  Description of the job to be processed by HuCore Consisting of owner
  information, a parameter setting, a task setting and a list of image files.
*/
class JobDescription {

  /*!
    \var    $id
    \brief  A unique id identifying the job
  */
  private $id;

  /*!
    \var    $parameterSetting
    \brief  The Job's ParameterSetting
  */
  public $parameterSetting;

  /*!
    \var    $taskSetting
    \brief  The Job's TaskSetting
  */
  public $taskSetting;

  /*!
    \var    $analysisSetting
    \brief  The Job's AnalysisSetting
  */
  public $analysisSetting;

  /*!
    \var    $files
    \brief  The list of files to be processed by the Job
  */
  private $files;

  /*!
   \var     $autoseries
   \brief   Boolean, whether or not to load a series automatically.
  */
  private $autoseries;

  /*!
    \var    $owner
    \brief  The user who created the Job
  */
  private $owner;               // owner            User

  /*!
    \var    $message
    \brief  The last error message
  */
  private $message;

  /*!
    \var    $pass (integer)
    \brief  Pass 1 or 2 of step combined processing
    \todo   Check: this is not used any more!
  */
  private $pass;

  /*!
    \var    $group
    \brief  Name of the group to be used
  */
  private $group;

  /*!
    \var    $jobType
    \brief  Name of the job type: hucore, deletejobs, etc.
  */
  private $jobType;

  /*!
    \var    $jobID 
    \brief  ID of the job to delete, prioritize, etc.
  */
  private $jobID;
  

  
  /*!
    \brief Constructor
  */
  public function __construct() {
    $this->id = (string)(uniqid(''));
    $this->message = "";
    $this->pass = 1;
  }

  /*!
    \brief Returns last error message
    \return last error message
  */
  public function message() {
    return $this->message;
  }

  /*!
    \brief Returns the unique id that identifies the Job
    \return unique id
  */
  public function id() {
    return $this->id;
  }

  /*!
    \brief Sets the (unique) id of the Job
    \param  $id Unique id
  */
  public function setId($id) {
    $this->id = $id;
  }

  /*!
    \brief Returns the name of owner of the job
    \return name of the owner
  */
  public function owner() {
    return $this->owner;
  }

  /*!
    \brief Sets the owner of the Job
    \param  $owner Name of the owner of the Job
  */
  public function setOwner($owner) {
    $this->owner = $owner;
  }

  /*!
    \brief Returns the ParameterSetting associated with the job
    \return a ParameterSetting object
  */
  public function parameterSetting() {
    return $this->parameterSetting;
  }

  /*!
    \brief Returns the TaskSetting associated with the job
    \return a TaskSetting object
  */
  public function taskSetting() {
    return $this->taskSetting;
  }

  /*!
    \brief Returns the AnalysisSetting associated with the job
    \return an AnalysisSetting object
  */
  public function analysisSetting() {
    return $this->analysisSetting;
  }

  /*!
    \brief Returns the files associated with the job
    \return array of file names
  */
  public function files() {
    return $this->files;
  }

  /*!
    \brief Returns the automatically load series mode of the job
    \return boolean: true or false
  */
  public function autoseries() {
    return $this->autoseries;
  }

  /*!
    \brief Sets the ParameterSetting for the job
    \param $setting A ParameterSetting object
  */
  public function setParameterSetting( ParameterSetting $setting) {
    $this->parameterSetting = $setting;
    $this->owner = $setting->owner();
  }

  /*!
    \brief Sets the TaskSetting for the job
    \param $setting A TaskSetting object
  */
  public function setTaskSetting( TaskSetting $setting) {
    $this->taskSetting = $setting;
  }

  /*!
    \brief Sets the AnalysisSetting for the job
    \param $setting An AnalysisSetting object
  */
  public function setAnalysisSetting( AnalysisSetting $setting) {
    $this->analysisSetting = $setting;
  }

  /*!
    \brief Sets the list of files for the job
    \param $files Array of file names
    \param $autoseries whether or not file series should be loaded automatically
  */
  public function setFiles($files, $autoseries = FALSE) {
    $this->files = $files;
    $this->autoseries = $autoseries;
  }

  /*!
    \brief Returns the group of the user associated with the job
    \return group of the user
  */
  public function group() {
    return $this->group;
  }

  /*!
    \brief Sets the group of the user associated with the job
    \param  $group  Group of the user
  */
  public function setGroup($group) {
    $this->group = $group;
  }

  /*!
   \brief	Returns the Huygens template name containing the unique HRM id
   \return	the template name
  */
  public function getHuTemplateName() {
      return ".hrm_" . $this->id() . ".hgsb";
  }

  /*!
   \brief       Returns the name of the job controller to be used by GC3Pie
   \return      The controller name with a unique HRM id 
  */
  public function getGC3PieControllerName() {
    return "gc3_" . $this->id() . ".cfg";
  }

  /*!
    \brief
    \return
  */
  public function setJobID( $jobID ) {
      $this->jobID = $jobID;
  }

  /*!
    \brief
    \return
  */
  public function getJobID( ) {
      return $this->jobID;
  }
  
  /*!
    \brief      Sets the job type as an object property.
    \params     $jobType The name of the job type: hucore, deletejobs
  */
  public function setJobType( $jobType ) {
      switch( $jobType )  {
      case 'hucore':
      case 'deletejobs':
          $this->jobType =  $jobType;
          break;
      default:
          error_log("Unimplemented job type $jobType.");
      }
  }

  /*!
    \brief      The job type to write in the GC3Pie config  file.
    \return     The job type.
    */
    public function getJobType( ) {
        return $this->jobType;
    }

  /*!
    \brief Create a Job from this JobDescription
    \return true if the Job could be created, false otherwise
  */
  public function createJob() {
    $result = True;
    $jobParameterSetting = new JobParameterSetting();
    $jobParameterSetting->setOwner($this->owner);
    $jobParameterSetting->setName($this->id);
    $jobParameterSetting->copyParameterFrom($this->parameterSetting);
    $result = $result && $jobParameterSetting->save();

    $taskParameterSetting = new JobTaskSetting();
    $taskParameterSetting->setOwner($this->owner);
    $taskParameterSetting->setName($this->id);
    $taskParameterSetting->copyParameterFrom($this->taskSetting);
    $result = $result && $taskParameterSetting->save();

    $analysisParameterSetting = new JobAnalysisSetting();
    $analysisParameterSetting->setOwner($this->owner);
    $analysisParameterSetting->setName($this->id);
    $analysisParameterSetting->copyParameterFrom($this->analysisSetting);
    $result = $result && $analysisParameterSetting->save();

    $db = new DatabaseConnection();
    $result = $result && $db->saveJobFiles($this->id,
                                           $this->owner,
                                           $this->files,
                                           $this->autoseries);

    $queue = new JobQueue();
    $result = $result && $queue->queueJob($this);
    if (!$result) {
      $this->message = "Could not create job!";
    }
    return $result;
  }


  /*!
    \brief Loads a JobDescription from the database for the user set in
          this JobDescription
  */
  public function load() {
    $db = new DatabaseConnection();

    $parameterSetting = new JobParameterSetting;
    $owner = new User();
    $name = $db->userWhoCreatedJob($this->id);
    $owner->setName($name);
    $parameterSetting->setOwner($owner);
    $parameterSetting->setName($this->id);
    $parameterSetting = $parameterSetting->load();
    $this->setParameterSetting($parameterSetting);

    $taskSetting = new JobTaskSetting;
    $taskSetting->setNumberOfChannels($parameterSetting->numberOfChannels());
    $taskSetting->setName($this->id);
    $taskSetting->setOwner($owner);
    $taskSetting = $taskSetting->load();
    $this->setTaskSetting($taskSetting);

    $analysisSetting = new JobAnalysisSetting;
    $analysisSetting->setNumberOfChannels($parameterSetting->numberOfChannels());
    $analysisSetting->setName($this->id);
    $analysisSetting->setOwner($owner);
    $analysisSetting = $analysisSetting->load();
    $this->setAnalysisSetting($analysisSetting);

    $this->setFiles($db->getJobFilesFor($this->id()),
                    $db->getSeriesModeForId($this->id()));
  }

  /*!
    \brief Copies from another JobDescription into this JobDescription
    \param  $aJobDescription  Another JobDescription
  */
  public function copyFrom( JobDescription $aJobDescription ) {
    $this->setParameterSetting($aJobDescription->parameterSetting());
    $this->setTaskSetting($aJobDescription->taskSetting());
    $this->setAnalysisSetting($aJobDescription->analysisSetting());
    $this->setOwner($aJobDescription->owner());
    $this->setGroup($aJobDescription->group());
  }

  /*!
    \brief Checks whether the JobDescription describes a compound Job
    \return true if the Job is compound (i.e. contains more than one file),
    false otherwise
  */
  public function isCompound() {
    if (count($this->files)>1) {
      return True;
    }
    return False;
  }

  /*!
    \brief Create elementare Jobs from compound Jobs
    \return true if elementary Jobs could be created, false otherwise
  */
  public function createSubJobs() {
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    return $this->createSubJobsforFiles();
  }

  /*!
    \brief Returns the full file name without redundant slashes
    \return full file name without redundant slashes
    \todo Isn't this redundant? One could use the FileServer class
  */
  public function sourceImageName() {
    $files = $this->files();
    // avoid redundant slashes in path
    $result = $this->sourceFolder() . preg_replace("#^/#", "", end($files));
    return $result;
  }

  /*!
    \brief Returns the file name without path
    \return file name without path
    \todo What about redundant slashes?
  */
  public function sourceImageNameWithoutPath() {
    $name = $this->sourceImageName();
    $pos = strrpos( $name, '/' );
    if ( $pos ) {
      return ( substr( $name, ( $pos + 1 ) ) );
    } else {
      return $name;
    }
  }

  /*!
    \brief Returns relative source path (under the image source path)
    \return relative source path
  */
  public function relativeSourcePath() {
    $files = $this->files();
    $inputFile = end($files);
    $inputFile = explode("/", $inputFile);
    array_pop($inputFile);
    $path = implode("/", $inputFile);
    if (strlen($path) > 0) {
        // make sure to have exactly ONE slash at the end of $path:
        $path = preg_replace("#/*$#", "/", $path);
    }
    return $path;
  }

  /*!
    \brief Returns the file base name. Special handling for LIF and CZI files.
    \return file base name
  */
  public function sourceImageShortName() {
    $files = $this->files();
    $inputFile = end($files);
    $inputFile = explode("/", $inputFile);
    // remove file extension
    //$inputFile = explode(".", end($inputFile));
    //$inputFile = $inputFile[0];
    $parameterSetting = $this->parameterSetting;
    $parameter = $parameterSetting->parameter('ImageFileFormat');
	$fileFormat = $parameter->value();
    if (preg_match("/^(.*)\.(lif|czi)\s\((.*)\)/i", $inputFile[0], $match)) {
        $inputFile = $match[ 1 ] . '_' . $match[ 2 ];
    } else {
        $inputFile = substr(end($inputFile), 0, strrpos(end($inputFile), "."));
    }
  
    return $inputFile;
  }

  /*!
    \brief Returns the source folder name
    \return source folder name
  */
  public function sourceFolder() {
    global $huygens_server_image_folder;
    global $image_source;
    $user = $this->owner();
    $result = $huygens_server_image_folder .
        $user->name() . "/" . $image_source . "/";
    return $result;
  }


  /*!
    \brief Returns the destination image name without extension and without path
    \return destination image name without extenstion and without path
  */
  public function destinationImageName() {
    $taskSetting = $this->taskSetting();
    $files = $this->files();
    $outputFile = $this->sourceImageShortName();
    $outputFile = end(explode($taskSetting->name(), $this->sourceImageShortName()));
    $outputFile = str_replace(" ","_",$outputFile);
    $result = $outputFile . "_" . $taskSetting->name() . "_hrm";
        # Add a non-numeric string at the end: if the task name ends with a
        # number, that will be removed when saving using some file formats that
        # use numbers to identify Z planes. Therefore the result file won't
        # be found later and an error will be generated.
    return $result;
  }

  /*!
    \brief Returns the destination image name without path and with output file format extension
    \return destination image name without path and with output file format extension
  */
  public function destinationImageNameWithoutPath() {
    $name = $this->destinationImageName();
    $pos = strrpos( $name, '/' );
    if ( $pos ) {
      $name = substr( $name, ( $pos + 1 ) );
    }
    // Append extension
    $taskSetting = $this->taskSetting();
    $param = $taskSetting->parameter('OutputFileFormat');
    $fileFormat = $param->extension( );
    return ( $name . "." . $fileFormat );
  }

  /*!
    \brief Returns the destination image name with selected output extension and relative path
    \return destination image name with selected output extension and relative path
  */
  public function destinationImageNameAndExtension() {
    $name = $this->destinationImageName();
    // Append extension
    $taskSetting = $this->taskSetting();
    $param = $taskSetting->parameter('OutputFileFormat');
    $fileFormat = $param->extension( );
    return ( $this->relativeSourcePath(). $name . "." . $fileFormat );
  }

  /*!
    \brief Returns the destination image file name with full path
    \return destination image file name with full path
  */
  public function destinationImageFullName() {
    $result = $this->destinationFolder() . $this->destinationImageName();
    return $result;
  }

  /*!
    \brief  Returns the final destination folder name (also considering
            sub-folders created by the user in the image destination)
    \return destination folder name
  */
  public function destinationFolder() {
    global $huygens_server_image_folder;
    global $image_destination;

    $user = $this->owner();

    // Make sure to get rid of blank spaces in the folder name!
    $relSrcPath = $this->relativeSourcePath();
    $relSrcPath = str_replace( " ", "_", $relSrcPath );

    // avoid redundant slashes in path
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $relSrcPath;

    return $result;
  }

  /*!
   \brief     Convenience function to get all the parameters of the task setting.
   \return    A string with all the parameters of the task setting.
  */
  public function taskSettingAsString( ) {
      $numChannels = $this->parameterSetting->numberOfChannels();
      $micrType = $this->parameterSetting->microscopeType();

      return $this->taskSetting()->displayString( $numChannels, $micrType );
  }


/*
                              PRIVATE FUNCTIONS
*/

  /*!
    \brief Create elementary Jobs from multi-file compound Jobs
    \return true if elementary Jobs could be created, false otherwise
  */
  private function createSubJobsforFiles() {
    $result = True;
    foreach ($this->files as $file) {
      // error_log("file=".$file);
      $newJobDescription = new JobDescription();
      $newJobDescription->copyFrom($this);
      $newJobDescription->setFiles(array($file),$this->autoseries);
      $result = $result && $newJobDescription->createJob();
    }
    return $result;
  }

  /*!
    \brief  Checks whether a string ends with a number
    \return true if the string ends with a number, false otherwise
  */
  private function endsWithNumber($string) {
    $last = $string[strlen($string)-1];
    return is_numeric($last);
  }

}
