<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Parameter.inc");
require_once("Database.inc");
require_once("User.inc");
require_once("Shell.inc");
require_once("hrm_config.inc");
require_once("Job.inc");
require_once("JobQueue.inc");

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
    \var    $files
    \brief  The list of files to be processed by the Job
  */
  private $files;

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
  
  //public $rangeParameters;     // why not use a global variable from the beginning?!

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
    \brief Returns the files associated with the job
    \return array of file names
  */
  public function files() {
    return $this->files;
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
    \brief Sets the list of files for the job
    \param $files Array of file names
  */
  public function setFiles($files) {
    $this->files = $files;
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
    \brief Add a Job to the queue
    \return true if the Job could be added to the queue, false otherwise
  */
  public function addJob() {
    // =========================================================================
    //
    // In previous versions of the HRM, the web interface would create compound
    // jobs that the queue manager would then process. Now, this task has become
    // responsibility of the web interface.
    //
    // =========================================================================

    $result = True;

    $lqueue = new JobQueue();
    $lqueue->lock();

    // createJob() function was originally called directly
    $result = $result && $this->createJob();

    if ( $result ) {

      // Process compound jobs
      $this->processCompoundJobs( );

      // Assign priorities
      $db = new DatabaseConnection();
      $result = $db->setJobPriorities();
      if ( !$result ) {
        error_log( "Could not set job priorities!" );
      }
    }

    $lqueue->unlock();

    return $result;
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
    $db = new DatabaseConnection();
    $result = $result && $db->saveJobFiles($this->id, $this->owner, $this->files);
    $queue = new JobQueue();
    $result = $result && $queue->queueJob($this);
    if (!$result) {
      $this->message = "create job - database error!";
    } 
    return $result;
  }
  
  /*!
    \brief Processes compound Jobs to deliver elementary Jobs
    
    A compound job contains multiple files.
  */
  public function processCompoundJobs() {
    $queue = new JobQueue();
    $compoundJobs = $queue->getCompoundJobs();
    foreach ($compoundJobs as $jobDescription) {
      $job = new Job($jobDescription);
      $job->createSubJobsOrScript();
    }
  }
  
  /*!
    \brief Loads a JobDescription from the database for the user set in
          this JobDescription
  */
  public function load() {
    $db = new DatabaseConnection();
    $parameterSetting = new JobParameterSetting;
    $owner = new User;
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
    $this->setFiles($db->getJobFilesFor($this->id()));    
  }

  /*!
    \brief Copies from another JobDescription into this JobDescription
    \param  $aJobDescription  Another JobDescription
  */
  public function copyFrom( JobDescription $aJobDescription ) {
    $this->setParameterSetting($aJobDescription->parameterSetting());
    $this->setTaskSetting($aJobDescription->taskSetting());
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
    $result = $this->sourceFolder() . ereg_replace("^/", "", end($files));
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
    // avoid redundant slashes in path
    if (strlen($path) > 0) $path = ereg_replace("([^/])$", "\\1/", $path);
    return $path;
  } 

  /*!
    \brief Returns the file base name with some special handling for Lif files
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
    if ( strcasecmp( $fileFormat, 'lif' ) == 0 ) {
      if ( preg_match("/^(.*)\.lif\s\((.*)\)/i", $inputFile[0], $match) ) {
        $inputFile = $match[ 1 ] . '_' . $match[ 2 ];
      } else {
        $inputFile = substr(end($inputFile), 0, strrpos(end($inputFile), ".")); }
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
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_source . "/";
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

    if ($taskSetting->isStepCombinedProcessing()) {
      if ($this->pass == 1) {
          $extension = 'A';
      } else {
          $extension = 'B';
      } 
      $result = $result . "_step" . $extension;
    } 
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
    global $resultImagesRenamed;
    // use source filename as destination filename
    if (!$resultImagesRenamed) {
      $files = $this->files();
      $result = $this->destinationFolder() . ereg_replace("^/", "", end($files));
      $result = substr($result, 0, strrpos($result, "."));
      if ( $this->endsWithNumber($result) ) {
          // Avoid output files ending in a number.
          $result .= "_hrm";
      }
    }
    else {
      $result = $this->destinationFolder() . $this->destinationImageName();
    }
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
    // avoid redundant slashes in path
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $this->relativeSourcePath();
    
    return $result;
  } 

/*
                              PRIVATE FUNCTIONS
*/

  /*!
    \brief Create elementare Jobs from multi-file compound Jobs
    \return true if elementary Jobs could be created, false otherwise
  */
  private function createSubJobsforFiles() {
    $result = True;
    foreach ($this->files as $file) {
      // error_log("file=".$file);
      $newJobDescription = new JobDescription();
      $newJobDescription->copyFrom($this);
      $newJobDescription->setFiles(array($file));	
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
