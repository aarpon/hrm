<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("Setting.inc");
require_once ("Database.inc");
require_once ("JobDescription.inc");
require_once ("hrm_config.inc");
require_once ("Fileserver.inc");
require_once ("Shell.inc");
require_once ("Mail.inc");
require_once ("JobTranslation.inc");
require_once ("System.inc");

/*!
 \class Job
 \brief	Stores all information for a deconvolution Job
 */
class Job {
  private $script;
  private $jobDescription;
  private $server;
  private $pid;
  private $status;

  /*!
   \brief	Constructor
   \param	$jobDescription	JobDescrition object
   */
  public function __construct($jobDescription) {
    $this->jobDescription = $jobDescription;
    $this->script = '';
  }

  /*!
   \brief	Returns the JobDescription associated with the Job
   \return	JobDescription object
   */
  public function description() {
    return $this->jobDescription;
  }

  /*!
   \brief	Sets the server which will run the Job
   \param	$server	Server name
   */
  public function setServer($server) {
    $this->server = $server;
  }

  /*!
   \brief	Creates a script for elementary jobs or splits compound jobs
   \return	for elementary jobs, returns true if the script was generated
   successfully, or false otherwise; for compound jobs, it always
   returns false
   */
  public function createSubJobsOrScript() {
    $result = True;
    $desc = $this->jobDescription;
    # print "<pre>"; print_r($desc); print "</pre>"; print ($desc->isCompound());
    if ($desc->isCompound()) {
      $result = $result && $desc->createSubJobs();
      if ($result) {
        error_log("created sub jobs");
        report("created sub jobs", 1);
      }
      if ($result) {
        $queue = new JobQueue();
        $result = $result && $queue->removeJob($desc);
        if ($result)
        error_log("removed compound job");
        report("removed compound job\n", 1);
        // TODO: check if this does fix compound job processing
        $result = False;
      }
    } else {
      report("Job is elementary", 1);
      $this->createScript();
      report("Created script", 1);
      $result = $result && $this->writeScript();
      /*if ($result) {
       report("Wrote script", 1);
       }*/
    }
    return $result;
  }

  /*!
   \brief	Returns the name of the server associated with the Job
   \return	server name
   */
  public function server() {
    return $this->server;
  }

  /*!
   \brief	Returns the script generated for the Job
   \return	script
   */
  public function script() {
    return $this->script;
  }

  /*!
   \brief	Returns the process identifier associated with the Job
   \return	process identifier
   */
  public function pid() {
    return $this->pid;
  }

  /*!
   \brief	Returns the Job id
   \return	Job id
   */
  public function id() {
    $desc = $this->description();
    return $desc->id();
  }

  /*!
   \brief	Sets the process identifier associated with the Job
   \param	$pid	Process identifier
   */
  public function setPid($pid) {
    $this->pid = $pid;
  }

  /*!
   \brief	Returns the Job status
   \return	Job status
   */
  public function status() {
    return $this->status;
  }

  /*!
   \brief	Sets the status of the Job
   \param	$status	Status of the Job
   */
  function setStatus($status) {
    $this->status = $status;
  }

  /*!
   \brief	Creates a script
   */
  public function createScript() {

    // HuCore version 3.7.0 and above is required for HRM 1.3.0
    if ( System::huCoreVersion() >= 3070000 ) {
      $this->createScriptVersionOne();
    }

  }

  /*!
   \brief	A deconvolution script based on HuCore templates
   \todo	Rename this method to createScript() (and make it 
   			public) when the old script-generating functions
   			are gone
   */
  private function createScriptVersionOne() {

      $jobDescription = $this->description();
      $jobTranslation = new JobTranslation($jobDescription);
      $this->script = $jobTranslation->template;
  }

  /*!
   \brief	The original version of the deconvolution script
   	
   The original version of the deconvolution script, a linear one, with
   lines that were written or not depending on the task parameters.
   	
   This function should be deleted after the new one proves to be
   stable enough, to make this code clearer. The same applies to all
   the old script generating subroutines, like putMultiChannelScriptOn,
   putScriptOn and the like.
    
   \todo This function is deprecated and will be removed
   */
//  private function createScriptVersionZero() {
//    global $huscript_call;
//
//    $desc = $this->description();
//    $setting = $desc->parameterSetting();
//    $this->script = "set id [pid]" . "\n" . 'puts "\npid=$id"' . "\n";
//    $this->script = $this->script . "huOpt verb -mode noQs\n";
//    $this->script = $this->script .
//                    "if { [ catch {\n# A global catch to report important errors.\n";
//    $this->script = $this->script . "huOpt gundo off\n";
//    if ($setting->isMultiChannel() && ($setting->numberOfChannels() > 1)) {
//      $this->script = $this->script . $desc->putMultiChannelScriptOn('', true);
//    } else {
//      $this->script = $this->script . $desc->putScriptOn('', true);
//    }
//    $this->script = $this->script . "} err ] } { \n".
//                "huOpt printError \$err }\n";
//    $id = $desc->id();
//    $finishedMarker = $desc->sourceFolder() . '.finished_' . "$id";
//    $this->script = $this->script . "exec touch " . '"' .
//    $finishedMarker . '"' . "\n".
//            "file attributes \"$finishedMarker\" -permissions 0666\n".
//            "puts \"- DONE --------------------------------\\n\\n\"\n";
//    $this->script = $this->script . "exit\n";
//  }

  /*!
   \brief	Returns the script name
    
   The script name contains the id to make it univocal

   \return	the sript name
   */
  public function scriptName() {
    $desc = $this->description();
    $result = ".hrm_" . $desc->id() . ".tcl";
    return $result;
  }

  /*!
   \brief	Writes the script to the user's source folder
   \return	true if the script could be written, false otherwise
   */
  public function writeScript() {
    $result = True;
    $desc = $this->description();
    $scriptName = $this->scriptName();
    $user = $desc->owner();
    $username = $user->name();
    $fileserver = new Fileserver($username);
    $scriptPath = $fileserver->sourceFolder();
    $scriptFile = $scriptPath . "/" . $scriptName;
    $file = fopen($scriptFile, "w");
    if (! $file ) {
      report ("Error opening file $scriptFile, verify permissions!", 0);
      // If permissions fail, introduce some delay not to saturate the
      // log file!
      report ("Waiting 15 seconds...", 1);
      sleep(15);
      return False;
    } else {
      $result = $result && (fwrite($file, $this->script) > 0);
      fclose($file);
      report("Wrote script $scriptFile", 1);
    }
    return $result;
  }

  /*!
   \brief	Checks whether the result image is present in the destination directory
   \return	true if the result image could be found, false otherwise
   \todo Refactor
   */
  public function checkResultImage() {
    global $imageProcessingIsOnQueueManager;
    global $copy_images_to_huygens_server;
    global $huygens_user;
    global $huygens_group;
    global $huygens_server_image_folder;
    global $image_destination;

    clearstatcache();

    # $queue = new JobQueue();

    // Server name without proc number
    $server = $this->server;
    $s = split(" ", $server);
    $server_hostname = $s[0];

    $desc = $this->description();
    $user = $desc->owner();

    $fileserver = new Fileserver($user->name());
    $path = $fileserver->destinationFolderFor($desc);

    // TODO refactor JobDescription
    $destFileName = $desc->destinationImageNameWithoutPath();
    //$resultImage = $desc->sourceImageShortName() . "*" . "_" .
    //$desc->id() . "*";

    // If fileshare is not on the same host as Huygens
    if (!$imageProcessingIsOnQueueManager && $copy_images_to_huygens_server) {
      $image = $huygens_server_image_folder . $user->name() .
            	"/" . $image_destination . "/" .
      $desc->relativeSourcePath() . $destFileName .  "*";
      $previews = $huygens_server_image_folder .
      $user->name() . "/" . $image_destination . "/" .
      $desc->relativeSourcePath() . "hrm_previews/".
      $destFileName. "*";
      // escape special characters in image path
      $image = eregi_replace(" ", "\\ ", $image);
      $image = str_replace(".ics",".i*s", $image);
      $previews = eregi_replace(" ", "\\ ", $previews);
      //error_log("Retrieving result image...");
      //error_log("sudo mkdir -p " . escapeshellarg($path));
      $result = exec("sudo mkdir -p " . escapeshellarg($path));
      $result = exec("sudo mkdir -p " . escapeshellarg($path)
      . "/hrm_previews");
      //error_log($result);
      //error_log("(cd " . escapeshellarg($path) . " && scp " . $huygens_user . "@" . $server_hostname . ":" . escapeshellarg($image) . " .)");
      $result = exec("(cd " . escapeshellarg($path) . " && sudo scp " . $huygens_user . "@" . $server_hostname . ":" . escapeshellarg($image) . " .)");
      $result = exec("(cd " . escapeshellarg($path) .
                "/hrm_previews && sudo scp " . $huygens_user . "@" . $server_hostname . ":" . escapeshellarg($previews) . " .)");
      //error_log($result);
    }

    // TODO is checking for job id only a good idea?
    $fileNameExists = $fileserver->folderContains($path, $destFileName);

    // TODO is checking for new files a relevant criterion?
    //$newFileWritten = $fileserver->folderContainsNewerFile($path, $queue->startTime($this));
    $result = $fileNameExists/* || $newFileWritten*/;
    if (!$result) {
      report("Problem: no result file $destFileName in destination directory $path", 0);
    } else { report("File $destFileName available", 2); }
    return $result;
  }

  /*!
   \brief	Checks if the process is finished
   \return	true if the process is finished, false otherwise
   \todo Refactor
   */
  public function checkProcessFinished() {
    global $imageProcessingIsOnQueueManager;
    global $huygens_user;
    global $huygens_server_image_folder;
    global $image_source, $image_destination;

    clearstatcache();

    // Server name without proc number
    $server = $this->server;
    $s = split(" ", $server);
    $server_hostname = $s[0];

    $desc = $this->description();
    $user = $desc->owner();

    $fileserver = new Fileserver($user->name());
    $path = $fileserver->sourceFolder();
    $dpath = $fileserver->destinationFolderFor($desc);

    $finishedMarker = ".finished_" . $desc->id();
    $endTimeMarker = ".EstimatedEndTime_" . $desc->id();
    $remarksFile = $desc->sourceImageShortName() . "*" . "_" .
    $desc->id() . "*.remarks.txt";

    // If fileshare is not on the same host as Huygens.
    if (!$imageProcessingIsOnQueueManager) {
      // Copy the finished marker
      $marker = $huygens_server_image_folder . $user->name() .
                    "/" . $image_source . "/" . $finishedMarker;
      $remoteFile = exec("ssh " . $huygens_user . "@" .
      $server_hostname . " ls " . $marker);
      //error_log("ssh " . $huygens_user . "@" . $server_hostname . "
      //ls " . $marker);
      //error_log($result);
      // TODO: is the queue manager a sudoer?
      if ($remoteFile == $marker) {
        exec("(cd " . $path . " && sudo scp " . $huygens_user . "@"
        . $server_hostname . ":" . $marker . " .)");

        // If finished, copy also the remarks file.
        // Shouldn't this happen only if
        // $copy_images_to_huygens_server == true ?
        $marker = $huygens_server_image_folder . $user->name() .
                    "/" . $image_destination . "/" .
        $desc->relativeSourcePath() . $remarksFile;
        $marker = $huygens_server_image_folder .
        $user->name() . "/" .
        $image_destination . "/" . $remarksFile;
        $remoteFile = exec("ssh " . $huygens_user . "@" .
        $server_hostname . " ls " . $marker);
        if ($remoteFile == $marker) {
          exec("(cd " . $dpath . " && sudo scp " .
          $huygens_user . "@" . $server_hostname . ":" .
          $marker . " .)");
        }
      } else {

        // Copy the estimated end time little file.
        $marker = $huygens_server_image_folder . $user->name()
        . "/" .  $image_source . "/" . $endTimeMarker;
        $remoteFile = exec("ssh " . $huygens_user . "@" .
        $server_hostname . " ls " . $marker);
        if ($remoteFile == $marker) {
          exec("(cd " . $path . " && sudo scp " . $huygens_user
          . "@" . $server_hostname . ":" . $marker . " .)");
          // Delete in the remote place, not to transfer again
          // until it is updated.
          exec("ssh " . $huygens_user . "@" .
          $server_hostname . " rm -f " . $marker);
        }
      }
    }
    $result = file_exists($path . '/' . $finishedMarker);

    if ($imageProcessingIsOnQueueManager) {
      $proc = newExternalProcessFor($this->server(), $this->server().
                "_" .$this->id() . "_out.txt", $this->server() .  "_"
                .$this->id(). "_error.txt");
                $result = !$proc->existsHuygensProcess($this->pid());
                if (!$result && $proc->isHuygensProcessSleeping($this->pid())) {
                  $proc->rewakeHuygensProcess($this->pid());
                }
    }

    if ( !$result && file_exists($path . '/' . $endTimeMarker) ) {
      // Tasks may report an estimated end time, whenever they can.
      $estEndTime = file_get_contents($path . '/' . $endTimeMarker);
      report("Estimated end time for ". $desc->id(). ": $estEndTime", 1);
      $queue = new JobQueue();
      $queue->updateEstimatedEndTime($desc->id(), $estEndTime );
      // Delete the end time file, to only look at it when the
      // estimation is updated.
      @unlink($path . '/' . $endTimeMarker);
      # $this->UpdateEstimatedEndTime($estEndTime);
    }

    return $result;
  }
}

?>
