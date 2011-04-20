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
      $previews = $huygens_server_image_folder;
      $previews .= $user->name() . "/" . $image_destination . "/";
      $previews .= $desc->relativeSourcePath() . "hrm_previews/";
      $previews .= "*" . $desc->id() . "_hrm*";
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
    // HuCore replaces blanks with underscores.
    $path = str_replace(" ","_",$path);
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

    $finishedMarker = $desc->destinationImageName() . ".hgsb"; 
    $endTimeMarker = ".EstimatedEndTime_" . $desc->id();

    // Old code: to be removed.
    // $remarksFile = $desc->sourceImageShortName() . "*" . "_" .
    // $desc->id() . "*.remarks.txt";

    // If fileshare is not on the same host as Huygens.
    if (!$imageProcessingIsOnQueueManager) {


        // Old code: to be removed.
        // $marker = $huygens_server_image_folder . $user->name() .
        // "/" . $image_destination . "/" . $finishedMarker;

        // Copy the finished marker
        $marker = $dpath . $finishedMarker;
        $remoteFile = exec("ssh " . $huygens_user . "@" .
                           $server_hostname . " ls " . $marker);

        // Old code: to be removed.
        //error_log("ssh " . $huygens_user . "@" . $server_hostname . "
        //ls " . $marker);
        //error_log($result);

        // TODO: is the queue manager a sudoer?
        if ($remoteFile == $marker) {
            if (!file_exists($dpath)) {
                $result = exec("sudo mkdir -p " . escapeshellarg($dpath));
            }
            exec("(cd " . $dpath . " && sudo scp " . $huygens_user . "@"
                 . $server_hostname . ":" . $marker . " .)");
            
            // Old code: to be removed.
            // If finished, copy also the remarks file.
            // Shouldn't this happen only if
            // $copy_images_to_huygens_server == true ?
            //         $marker = $huygens_server_image_folder . $user->name() .
            //                     "/" . $image_destination . "/" .
            //         $desc->relativeSourcePath() . $remarksFile;
            //         $marker = $huygens_server_image_folder .
            //         $user->name() . "/" .
            //         $image_destination . "/" . $remarksFile;
            //         $remoteFile = exec("ssh " . $huygens_user . "@" .
            //         $server_hostname . " ls " . $marker);
            //         if ($remoteFile == $marker) {
            //           exec("(cd " . $dpath . " && sudo scp " .
            //           $huygens_user . "@" . $server_hostname . ":" .
            //           $marker . " .)");
            //         }
            $this->renameHuygensOutputFiles();
            $this->makeJobParametersFile();
        } else {
            
            // Old code: to be removed.
            // Copy the estimated end time little file.
            // $marker = $huygens_server_image_folder . $user->name()
            // . "/" .  $image_source . "/" . $endTimeMarker;
            // $remoteFile = exec("ssh " . $huygens_user . "@" .
            // $server_hostname . " ls " . $marker);
            // if ($remoteFile == $marker) {
            // exec("(cd " . $path . " && sudo scp " . $huygens_user
            // . "@" . $server_hostname . ":" . $marker . " .)");
            
            // // Delete in the remote place, not to transfer again
            // // until it is updated.
            // exec("ssh " . $huygens_user . "@" .
            // $server_hostname . " rm -f " . $marker);
            //  }
        }
    }

    $result = file_exists($dpath . $finishedMarker);

    if ($imageProcessingIsOnQueueManager) {
      $proc = newExternalProcessFor($this->server(), $this->server().
                "_" .$this->id() . "_out.txt", $this->server() .  "_"
                .$this->id(). "_error.txt");
                $result = !$proc->existsHuygensProcess($this->pid());

                // Notice that the job is finished if $result = true.
                if (!$result && $proc->isHuygensProcessSleeping($this->pid())) {
                  $proc->rewakeHuygensProcess($this->pid());
                } elseif ($result) {
                    $this->renameHuygensOutputFiles();
                    $this->makeJobParametersFile();
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


  /* -------- Utilities for renaming and formatting job specific files ------ */

  /*
   \brief       Renames the Huygens deconvolution default files 
   \brief       with a name that contains the job id and is HRM-compliant.
  */
  private function renameHuygensOutputFiles( ) {

      // Rename the huygens job main file.
      $huygensOut = $this->getHuygensDefaultFileName("main");
      $idJobOutFile = $this->getJobIdOutputFile();
      $this->renameFile($huygensOut,$idJobOutFile);

      // The Huygens history file will be removed rather than renamed.
      $historyFile = $this->getHuygensDefaultFileName("history");
      $this->removeFile($historyFile);
  }

  /*
   \brief       Removes a file with name $fileName.
   \param       $fileName The file name
  */
  private function removeFile($fileName) {
      global $imageProcessingIsOnQueueManager;
      global $huygens_user;

      // Build a rename command involving the old and new names.
      $cmd  = "if [ -f \"" . $fileName . "\" ]; ";
      $cmd .= "then ";
      $cmd .= "rm \"" . $fileName . "\"; ";
      $cmd .= "fi";

      // Proceed to execute the command:  remove the file.
      if ($imageProcessingIsOnQueueManager) {
          $result = exec($cmd);
      } else {
          $cmd = "'$cmd'";
          $server = split(" ", $this->server);
          $server_hostname = $server[0];
          $result = exec("ssh ".$huygens_user."@".$server_hostname." ".$cmd);
      }
  }

  /*
   \brief       Renames a file with name $oldName with a new name $newName.
   \param       $oldName The old file name
   \param       $newName The new file name
  */
  private function renameFile($oldName,$newName) {
      global $imageProcessingIsOnQueueManager;
      global $huygens_user;

      // Build a rename command involving the old and new names.
      $cmd  = "if [ -f \"" . $oldName . "\" ]; ";
      $cmd .= "then ";
      $cmd .= "mv \"" . $oldName . "\" \"" . $newName . "\"; ";
      $cmd .= "fi";

      // Proceed to execute the command:  rename the Huygens default output file.
      if ($imageProcessingIsOnQueueManager) {
          $result = exec($cmd);
      } else {
          $cmd = "'$cmd'";
          $server = split(" ", $this->server);
          $server_hostname = $server[0];
          $result = exec("ssh ".$huygens_user."@".$server_hostname." ".$cmd);
      }
  }

  /*
   \brief       Get the name and path of a file that includes the job id
   \brief       with an "inf" suffix.
   \return      The file path and name.
  */
  private function getJobIdOutputFile( ) {
      $jobDescription = $this->description();
      $idJobOutFile = $jobDescription->destinationImageName() . ".inf.txt";
      return $jobDescription->destinationFolder() . $idJobOutFile;
  }
  
  /*
  \brief        Get the name and path of the Huygens default file.
  \param        $fileType Which Huygens file.
  \return       The path and name of the Huygens default file.
  */
  private function getHuygensDefaultFileName($fileType) {
      $jobDescription = $this->description();
      $destFolder = $jobDescription->destinationFolder();

      switch ( $fileType ) {
      case "main":
          $huygensOut   = "scheduler_client0.log";
          $fileName = $destFolder . $huygensOut;
          break;
      case "history":
          $fileName = $jobDescription->destinationImageName();
          $fileName .= "_history.txt";
          $fileName = $destFolder . $fileName;
          break;
      default:
          error_log("Unknown file type.");
      }

      return $fileName;
  }

  /*
   \brief       Filters the Huygens deconvolution output file to leave
   \brief       a second file containing only the deconvolution parameters.
  */
  private function makeJobParametersFile( ) {
      $jobInformation = $this->readJobInformationFile();
      if (isset($jobInformation)) {
          $parsedInfoFile = $this->parseInfoFile($jobInformation);
          $paramFileName = $this->getParamFileName();
          $this->copyString2File($parsedInfoFile,$paramFileName);
          $this->copyFile2Host($paramFileName);
      }
  }

  /*
   \brief       Extracts important parameter data from the Huygens report file. 
   \param       $jobInformation The contents of the report file in an array.
   \return      The parameters in a formatted way.
  */
  private function parseInfoFile ($jobInformation) {

      $numberOfChannels = $this->getNumberOfChannels();
      $parsedInfoFile = $this->parseInfo2Table($jobInformation,"All");
      for ($chanCnt = 0; $chanCnt < $numberOfChannels;$chanCnt++) {
          $parsedInfoFile .= $this->parseInfo2Table($jobInformation,$chanCnt);
      }
      return $parsedInfoFile;
  }

  /*
   \brief       Gets the number of channels of he current job.
   \return      The number of channels.
  */
  private function getNumberOfChannels( ) {
      $jobDescription = $this->description();
      $microSetting = $jobDescription->parameterSetting;
      return $microSetting->numberOfChannels();
  }

  /*
   \brief       Copies a string to a file.
   \param       $string A variable containing a string.
   \param       $file The path and file where to copy the string.
  */
  private function copyString2File($string,$file) {
      $copy2File = fopen($file, 'w');
      fwrite($copy2File,$string);
      fclose($copy2File);
  }

  /*
   \brief       Gets a name for a file that will contain only the job parameters.
   \return      The file path and name
  */
  private function getParamFileName( ) {
      $jobDescription = $this->description();
      $paramFileName = $jobDescription->destinationImageName();
      $paramFileName .= ".parameters.txt";
      $paramFileName = $jobDescription->destinationFolder() . $paramFileName;
      return $paramFileName;
  }

  /*
   \brief       Copies a file from the current server to the processing server.
   \param       $fileName Path and name of the file on the local machine.
  */
  private function copyFile2Host($fileName) {
      global $imageProcessingIsOnQueueManager;
      global $huygens_user;

      // If working with remote servers, copy the file to the user destination.
      if (!$imageProcessingIsOnQueueManager) {
          $server = split(" ", $this->server);
          $server_hostname = $server[0];
          $cmd = "scp " . $fileName . " " .$huygens_user."@";
          $cmd .= $server_hostname.":".$fileName;
          $result = exec($cmd);
      } 
  }

  /*
   \brief       Read the Huygens deconvolution output file of this job id.
   \return      An array containing one array element per file line.
  */
  private function readJobInformationFile() {
      global $imageProcessingIsOnQueueManager;
      global $huygens_user;

      // Get the name of the job output file.
      $jobDescription = $this->description();
      $jobReportFile = $jobDescription->destinationImageName() . ".inf.txt";
      $jobReportFile = $jobDescription->destinationFolder() . $jobReportFile;

     // Proceed to read the job report file.
      $cmd = "cat \"" . $jobReportFile ."\"";
      if (!$imageProcessingIsOnQueueManager) {
          $server = split(" ", $this->server);
          $server_hostname = $server[0];
          $cmd = "ssh ".$huygens_user."@".$server_hostname." ".$cmd;
      }
      $result = exec($cmd,$HuJobInfFile);

      return $HuJobInfFile;
  }

  /*
   \brief       Parses the contents of the Huygens deconvolution output file
   \brief       to leave only the deconvolution parameters in a table.
   \param       $informationFile An array with the contents of the file.
   \param       $channel Which channel or All for channel indepenent parameters.
   \return      A string with the formatted table.
   \TODO        Report the scaling factors.
  */
  private function parseInfo2Table($informationFile,$channel) {

      $paramArray = array ( 'dx'            =>  'x pixel size',
                            'dy'            =>  'y pixel size',
                            'dz'            =>  'z step size',
                            'dt'            =>  'time interval',
                            'iFacePrim'     =>  '',
                            'iFaceScnd'     =>  '',
                            'objQuality'    =>  '',
                            'exBeamFill'    =>  '',
                            'imagingDir'    =>  '',
                            'pcnt'          =>  '',
                            'na'            =>  'numerical aperture',
                            'ri'            =>  'sample medium',
                            'ril'           =>  'objective type',
                            'pr'            =>  'pinhole size',
                            'ps'            =>  'pinhole spacing',
                            'ex'            =>  'excitation wavelength',
                            'em'            =>  'emission wavelength',
                            'micr'          =>  'microscope type');

      if ( $channel === "All" ) {

          if ($this->getNumberOfChannels() > 1) {
              $channel = "All";
          } else {
              $channel = "0";
          }

          $table = "";

          $pattern = "/{Microscope conflict for channel (.*):(.*)}}/";
          foreach ($informationFile as $fileLine) {
              preg_match($pattern,$fileLine,$matches);
              if (!empty($matches)) {
                  $parameter = $matches[1];
                  $warning = "WARNING:  MICROSCOPE CONFLICT FOR CHANNEL ";
                  $warning .= $matches[1] . ":";
                  $table .= $this->formatString("",20);
                  $table .= $this->formatString($warning,30);
                  $table .= $this->formatString($matches[2],30);
                  $table .= "\n";
   
              }
          }
          $table .= "\n\n";


          $table .= $this->formatString("PARAMETER",30);
          $table .= $this->formatString("VALUE",30);
          $table .= $this->formatString("CHANNEL",30);
          $table .= $this->formatString("SOURCE",30);
          $table .= "\n\n";

          $pattern = "/{Parameter ([a-z]+?) will be taken from template: (.*).}}/";
          foreach ($informationFile as $fileLine) {
              preg_match($pattern,$fileLine,$matches);
              if (!empty($matches)) {
                  $parameter = $matches[1];
                  if ($paramArray[$parameter] != "") {
                      $table .= $this->formatString($paramArray[$parameter],30);
                      $table .= $this->formatString($matches[2],30);
                      $table .= $this->formatString($channel,30);
                      $table .= $this->formatString("User defined",30);
                      $table .= "\n";
                  }
              }
          }

          $pattern = "/{Parameter ([a-z]+?) will be taken from metadata: (.*).}}/";
          foreach ($informationFile as $fileLine) {
              preg_match($pattern,$fileLine,$matches);
              if (!empty($matches)) {
                  $parameter = $matches[1];
                  if ($paramArray[$parameter] != "") {
                      $table .= $this->formatString($paramArray[$parameter],30);
                      $table .= $this->formatString($matches[2],30);
                      $table .= $this->formatString($channel,30);
                      $table .= $this->formatString("File metadata",30);
                      $table .= "\n";
                  }
              }
          }
      } else {
          $table = "";

          $pattern = "/{Parameter (.*) of channel $channel will be taken from ";
          $pattern .= "template: (.*).}}/";
          foreach ($informationFile as $fileLine) {
              preg_match($pattern,$fileLine,$matches);
              if (!empty($matches)) {
                  $parameter = $matches[1];
                  if ($paramArray[$parameter] != "") {
                      $table .= $this->formatString($paramArray[$parameter],30);
                      $table .= $this->formatString($matches[2],30);
                      $table .= $this->formatString($channel,30);
                      $table .= $this->formatString("User defined",30);
                      $table .= "\n";
                  }
              }
          }

          $pattern = "/{Parameter (.*) of channel $channel will be taken from ";
          $pattern .= "metadata: (.*).}}/";
          foreach ($informationFile as $fileLine) {
              preg_match($pattern,$fileLine,$matches);
              if (!empty($matches)) {
                  $parameter = $matches[1];
                  if ($paramArray[$parameter] != "") {
                      $table .= $this->formatString($paramArray[$parameter],30);
                      $table .= $this->formatString($matches[2],30);
                      $table .= $this->formatString($channel,30);
                      $table .= $this->formatString("File metadata",30);
                      $table .= "\n";
                  }
              }
          }
       }

      return $table;
  }

  /*  
   \brief       Formats a string with blanks on both sides.
   \param       $string The string to format
   \param       $pad The number of spaces to set on left and right
   \return      The formatted string
  */
  private function formatString($string,$pad) {
      return str_pad($string,$pad," ",STR_PAD_BOTH);
  }
}

?>
