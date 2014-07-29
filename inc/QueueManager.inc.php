<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("Setting.inc.php");
require_once ("Database.inc.php");
require_once ("JobDescription.inc.php");
require_once ("hrm_config.inc.php");
require_once ("Fileserver.inc.php");
require_once ("Shell.inc.php");
require_once ("Mail.inc.php");
require_once ("Job.inc.php");
require_once ("JobQueue.inc.php");
require_once ("System.inc.php");

/*!
 \class  QueueManager
 \brief  Creates Jobs from JobDescriptions and manages them in a priority queue
 */
class QueueManager {

    /*!
     \var   $queue
     \brief A JobQueue object
     */
    private $queue;

    /*!
    \var    $freeServer
    \brief  Name of a free server
    */
    private $freeServer;

    /*!
    \var	$job
    \brief	Job object
    */
    private $job;

    /*!
    \var	$shallStop
    \brief	Flag to indicate whether a Job should stop
    */
    private $shallStop;

    /*!
    \var	$stopTime
    \brief	Time of completion of a Job
    */
    private $stopTime;

    /*!
    \var	$nping
    \brief	Number of times a server has been pinged
    */
    private $nping;

    /*!
    \brief	Constructor: creates an initializes the QueueManager
    */
    public function __construct() {
        $this->runningJobs = array();
        $this->queue = new JobQueue();
        $this->shallStop = False;
        $this->nping = array();
    }

    /*!
    \brief  If existing, delete debris from previously run jobs.
    \param  $desc A JobDescription object.
    \param  $server_hostname The server where the job will be run.
    \TODO   Move to Shell.inc.php.
    */
    public function removeHuygensOutputFiles($desc, $server_hostname ) {
        global $imageProcessingIsOnQueueManager;
        global $huygens_user;

        // Get the Huygens default output file.
        $user = $desc->owner();
        $fileserver = new Fileserver($user->name());
        $destPath = $fileserver->destinationFolderFor($desc);
        $huyOutFile = $destPath . "scheduler_client0.log";

        // Build a remove command involving the file.
        $cmd  = "if [ -f \"" . $huyOutFile . "\" ]; ";
        $cmd .= "then ";
        $cmd .= "rm \"" . $huyOutFile . "\"; ";
        $cmd .= "fi";

        if ($imageProcessingIsOnQueueManager) {
            $result = exec($cmd);
        } else {
            $cmd = "'$cmd'";
            $result = exec("ssh ".$huygens_user."@".$server_hostname." ".$cmd);
        }
    }

    /*!
    \brief  Executes given Job
    \todo   Update templateName variable with templateName
    */
    public function executeTemplate( Job $job) {
        global $imageProcessingIsOnQueueManager;
        global $copy_images_to_huygens_server;
        global $logdir;

        $server = $this->freeServer;
        // server name without proc number
        $s = split(" ", $server);
        $server_hostname = $s[0];
        $desc = $job->description();
        $clientTemplatePath = $desc->sourceFolder();
        $templateName = $job->huTemplateName();

        // The new job must not get merged with debris from previously
        // failed jobs.
        $this->removeHuygensOutputFiles($desc, $server_hostname);

        report(">>>>> Executing template: " .
            $imageProcessingIsOnQueueManager . " " .
                $copy_images_to_huygens_server, 2);
        if (!$imageProcessingIsOnQueueManager &&
                $copy_images_to_huygens_server) {
            $clientTemplatePath =
                $this->copyImagesToServer($job, $server_hostname);
            report("images copied to IP server", 1);
        }

        $proc = newExternalProcessFor($server,
                                      $server . "_" .$job->id() . "_out.txt",
                                      $server . "_" . $job->id(). "_error.txt");
        report("shell process created", 1);

            /* Check whether the shell is ready to accept further execution. If
             not, the shell will be released internally, no need to release it
             here. */
        if (!$proc->runShell()) {
            return False;
        }

        report("running shell: $clientTemplatePath$templateName", 1);
        $pid = $proc->runHuygensTemplate($clientTemplatePath . $templateName);

        report("running template (pid $pid)", 1);

            /* The template in the background will keep running after release. */
        $proc->release();

        $job->setPid($pid);
        $job->setServer($server);
        return ($pid > 0);
    }

    /*!
    \brief  Grants permissions to deconvolved images and image previews
            to avoid conflicts between the previews generated from
            the website and the ones generated from the queue manager, as
            well as to guarantee that the files can always be deleted.

    \param       $desc A JobDescription object
    \param       $fileserver A Fileserver object.
    */
    private function chmodJob(JobDescription $desc, Fileserver $fileserver ) {

        // Build a subdirectory pattern to look for the source previews.
        $resultFiles = $desc->files();
        if (dirname($resultFiles[0]) == ".") {
            $previewSubdir = "/hrm_previews/";
        } else {
            $previewSubdir = "/" . dirname($resultFiles[0]) . "/hrm_previews/";
        }
        $subdirPreviewPattern = $previewSubdir. basename($resultFiles[0]) . "*";

        // Grant all permissions to the source preview in the source folder.
        $srcFolder = $fileserver->sourceFolder();
        $srcPreviews = $srcFolder . $subdirPreviewPattern;
        $this->chmodFiles(glob($srcPreviews),0777);
        $this->chmodFiles(dirname($srcPreviews),0777);

        // Find the results directory. Grant all permissions to it, if necessary
        $destFolder = $fileserver->destinationFolder();
        if (dirname($resultFiles[0]) == "." ) {
            $jobFileDir = $destFolder;
        } else {
            $subdir = str_replace(" ", "_", dirname($resultFiles[0]));
            $jobFileDir = $destFolder . "/" . $subdir;
            $this->chmodFiles($jobFileDir,0777);
        }

        // Build a file pattern to look for the deconvolved images.
        $jobFilePattern = $jobFileDir . "/" .
            $desc->destinationImageName() . "*";

        // Grant all permissions to the deconvolved images.
        $this->chmodFiles(glob($jobFilePattern),0777);

        // Build a file pattern to look for the preview results.
        $taskSetting = $desc->taskSetting();
        $jobFilePattern = dirname($jobFilePattern) . "/hrm_previews/";
        $jobFilePattern .= "*" . $taskSetting->name() . "_hrm*";

        // Grant all permissions the job previews.
        $this->chmodFiles(glob($jobFilePattern),0777);

        // Grant all permissions to the source preview in the destination folder
        $srcPreviews = $destFolder . str_replace(" ","_",$subdirPreviewPattern);
        $this->chmodFiles(glob($srcPreviews),0777);
        $this->chmodFiles(dirname($srcPreviews),0777);
    }

    /*!
    \brief  Changes file modes.
    \param  $files An array of files or single file.
    \param  $permission The requested file permission.
    */
    private function chmodFiles($files,$permission) {
        if (is_array($files)) {
            foreach ($files as $f) {
                chmod($f,$permission);
            }
        } else {
            chmod($files,$permission);
        }
    }

    /*!
    \brief	Copies the images needed by a given Job to the processing server
    \param	$job	A Job object
    \param	$server_hostname	Name of the server to which to copy the files
    \return	the full path to which the files were copied
    */
    public function copyImagesToServer( Job $job, $server_hostname) {
        global $huygens_user;
        global $image_folder;
        global $image_source;
        global $image_destination;
        global $huygens_server_image_folder;

        report("Copying images to IP server", 2);

        $desc = $job->description();
        $user = $desc->owner();

        // TODO substitute spaces by underscores in image name to avoid
        // processing problems with Huygens

        $batch = "cd \"" . $huygens_server_image_folder . "\"\n";
        $batch .= "-mkdir \"" . $user->name() . "\"\n";
        $batch .= "cd \"" . $user->name() . "\"\n";
        $batch .= "-mkdir \"" . $image_source . "\"\n";
        $batch .= "cd \"" . $image_source . "\"\n";
        $batch .= "put \"" . $image_folder . "/" . $user->name() . "/" .
            $image_source . "/" . $job->huTemplateName() . "\"\n";

        // Transfer the experimental PSF(s)
        $parameterSetting = $desc->parameterSetting;
        $psfParam = $parameterSetting->parameter('PointSpreadFunction');
        if ($psfParam->value() == "measured") {
            $psf = $parameterSetting->parameter('PSF');
            $values = $psf->value();
            foreach ($values as $value) {
                $path = split("/", $value);
                if (sizeof($path) > 0) {
                    for ($i = 0; $i < sizeof($path) - 1; $i++) {
                        $batch .= "-mkdir \"" . $path[$i] . "\"\n";
                        $batch .= "cd \"" . $path[$i] . "\"\n";
                    }
                }
                $filename = $image_folder . "/" . $user->name() . "/" .
                    $image_source . "/" . $value;
                if (stristr($filename, ".ics"))  {
                    $batch .= "put \"" . $filename . "\"\n";
                    $filename = eregi_replace(".ics", ".ids", $filename);
                    $batch .= "put \"" . $filename . "\"\n";
                } else {
                    $batch .= "put \"" . $filename . "\"\n";
                }
                if (sizeof($path) > 0) {
                    for ($i = 0; $i < sizeof($path) - 1; $i++) {
                        $batch .= "cd ..\n";
                    }
                }
            }
            $batch .= "cd \"" . $huygens_server_image_folder . "/" .
                $user->name() . "/" . $image_source . "\"\n";
        }

        $files = $desc->files();

        // Preprocess the files to check for lif files with subimages. If we
        // find some, we have to:
        // (1) remove the (subImage name);
        // (2) make sure to copy the files only once.
        $filteredFiles = array( );
        $counter = -1;
        foreach ($files as $file) {
            $counter++;
            $match = array( );
            if ( preg_match("/^(.*\.lif)\s\((.*)\)/i", $file, $match) ) {
                $filteredFiles[ $counter ] = $match[ 1 ];
            } else {
                $filteredFiles[ $counter ] = $file;
            }
        }
        $files = array_unique( $filteredFiles );

        // Now copy the files
        foreach ($files as $file) {
            $path = split("/", $file);
            if (sizeof($path) > 0) {
                for ($i = 0; $i < sizeof($path) - 1; $i++) {
                    $batch .= "-mkdir \"" . $path[$i] . "\"\n";
                    $batch .= "cd \"" . $path[$i] . "\"\n";
                }
            }
            $filename = $image_folder . "/" . $user->name() . "/" .
                $image_source . "/" . $file;
            if (stristr($filename, ".ics"))  {
                $batch .= "put \"" . $filename . "\"\n";
                $filename = substr($filename, 0,
                    strrpos($filename, '.ics')) . ".ids";
                $batch .= "put \"" . $filename . "\"\n";
            } else if (stristr($filename, ".tif") ||
                    stristr($filename, ".tiff")) {
                // TODO: if ImageFileFormat = single TIFF file, do not send
                // corresponding series
                $basename = preg_replace(
                    "/([^_]+|\/)(_)(T|t|Z|z|CH|ch)([0-9]+)(\w+)(\.)(\w+)/",
                        "$1$6$7", $filename);
                $name = preg_replace("/(.*)\.tiff?$/", "$1", $basename);
                $batch .= "put \"" . $name . "\"*\n";
            } else if (stristr($filename, ".stk")) {
                // if ImageFileFormat = STK time series, send all timepoints
                if (stripos($filename, "_t")) {
                    $basename = preg_replace(
                        "/([^_]+|\/)(_)(T|t)([0-9]+)(\.)(\w+)/",
                            "$1", $filename);
                    $batch .= "put \"" . $basename . "\"*\n";
                } else {
                    $batch .= "put \"" . $filename . "\"\n";
                }
            } else {
                $batch .= "put \"" . $filename . "\"\n";
            }
            if (sizeof($path) > 0) {
                for ($i = 0; $i < sizeof($path) - 1; $i++) {
                    $batch .= "cd ..\n";
                }
            }
        }

        $batch .= "cd ..\n";
        $batch .= "-mkdir \"" . $image_destination . "\"\n";
        $batch .= "quit\n";

        // report("\nBATCH \n$batch", 2);

        $batch_filename = $image_folder . "/" . $user->name() . "/" .
            "batchfile_" . $desc->id();

        $batchfile = fopen($batch_filename, 'w');
        fwrite($batchfile, $batch);
        fclose($batchfile);

        // TODO refactor this << move to Shell
        exec("sftp -b " . $batch_filename . " " . $huygens_user . "@" .
            $server_hostname);
        exec("rm -f " . $batch_filename);
        // >>

        return $huygens_server_image_folder . $user->name() . "/" .
            $image_source . "/";
    }

    /*!
    \brief	Returns the next Job in the queue
    \return	the next Job in the queue
    */
    public function nextJobFromQueue() {
        $queue = $this->queue;
        $foundExecutableJob = False;
        $pausedJobs = False;
        $jobDescription = $queue->getNextJobDescription();
        while ($jobDescription != NULL && !$foundExecutableJob) {
            $user = $jobDescription->owner();
            $username = $user->name();
            $fileserver = new Fileserver($username);
            if ($fileserver->isReachable()) {
                $foundExecutableJob = True;
            } else {
                $src = $fileserver->sourceFolder();
                $dest = $fileserver->destinationFolder();
                report("fileserver not reachable: $src or $dest".
                    "do not exist", 2);
                $pausedJobs = True;
                $queue->pauseJob($jobDescription);
                return NULL;
            }
            $jobDescription = $queue->getNextJobDescription();
        }
        if ($pausedJobs) {
            $queue->restartPausedJobs();
        }
        if ($jobDescription == NULL) {
            return NULL;
        }
        $job = new Job($jobDescription);
        $job->setServer($this->freeServer);
        return $job;
    }

    /*!
    \brief	Deletes temporary Job files from the file server
    \param	$job	A Job object
    */
    function cleanUpFileServer($job) {
        report("cleaning up file server", 1);
        $server = $job->server();
        // server name without proc number
        $s = split(" ", $server);
        $server_hostname = $s[0];
        $desc = $job->description();
        $user = $desc->owner();
        $username = $user->name();
        $fileserver = new Fileserver($username);
        $path = $fileserver->sourceFolder();
        $desc = $job->description();
        $queue = $this->queue;
        // finished. remove job, write email, clean up huygens server
        $id = $desc->id();
        $finishedMarker = $path . '/' . '.finished_' . "$id";
        if (file_exists($finishedMarker)) {
            report("removing finished marker", 1);
            unlink($finishedMarker);
        }
        $endTimeMarker = $path . '/' . '.EstimatedEndTime_' . "$id";
        if (file_exists($endTimeMarker)) {
            report("removing EstimatedEndTime report", 1);
            unlink($endTimeMarker);
        }
        // remove job
        $this->stopTime = $queue->stopJob($job);
        report("stopped job (" . date("l d F Y H:i:s") . ")\n", 1);
    }

    /*!
    \brief	Sets ownership of the files in the user area to the user
 	\param	$username	Name of the user (must be a valid linux user on
                        the file server)
 	*/
 	function restoreOwnership($username) {
        global $image_user;
        global $image_group;
        global $image_folder;

        $result = exec("sudo chown -R " . $image_user . ":" . $image_group .
            " " . $image_folder . "/" . $username);
        report("Restoring ownership... " . $result, 1);
    }

    /*!
     \brief   Checks whether the processing server reacts on 'ping'.
     \param   $server The server's name
     \param   $outLog A string specific of the output log file name.
     \param   $errLog A string specific of the error log file name.
     \return  Boolean: true on success.
    */
    private function isProcessingServerReachable($server,
                                                 $outLog = NULL,
                                                 $errLog = NULL) {
        if ( $outLog ) {
            $outLog .= "_";
        }

        if ( $errLog ) {
            $errLog .= "_";
        }

        $proc = newExternalProcessFor( $server,
                                       $server . $outLog . "_out.txt",
                                       $server . $errLog . "_error.txt" );
        $isReachable = $proc->ping();

        $proc->release();

        return $isReachable;
    }

    /*!
 	\brief	Updates the Job and server status

 	This methods kills all Jobs that are marked to be killed, checks whether
 	Jobs are completed and create report files, write Parameter files,...

 	\todo	This method is a mess!
    */
    public function updateJobAndServerStatus() {
        global $imageProcessingIsOnQueueManager;
        global $send_mail;
        global $logdir;

        // TODO check if it is necessary
        $queue = $this->queue;
        // Kill marked running jobs
        $queue->killMarkedJobs();
        // Remove broken jobs
        if ($queue->removeMarkedJobs()) {
            report("broken jobs removed", 2);
        }
        $runningJobs = $queue->runningJobs();
        if (count($runningJobs) > 0) {
            report(count($runningJobs) . " job" .
                (count($runningJobs) == 1 ? " is" : "s are") . " running", 2);
            // Because something is running, we are not in a hurry to continue.
            // Delay execution.
            sleep(5);
        }
        foreach ($runningJobs as $job) {
            $desc = $job->description();
            $user = $desc->owner();

            $fileserver = new Fileserver($user->name());
            if (!$fileserver->isReachable())
                continue;

            if ( !$this->isProcessingServerReachable($job->server(),
                                                     $job->id(),
                                                     $job->id()) ) {
                continue;
            }

            // Check finished marker
            $finished = $job->checkProcessFinished();

            if (!$finished) {
                continue;
            }

            report("checked finished process", 2);

            // Check result image
            $resultSaved = $job->checkResultImage();

            report("checked result image", 2);

            // Notify user
            $startTime = $queue->startTime($job);
            $errorFile = $logdir . "/" . $job->server() .
                "_" . $job->id() . "_error.txt";
            $logFile = $logdir . "/" . $job->server() .
                "_" . $job->id() . "_out.txt";

            if (!$resultSaved) {
                report("finishing job " . $desc->id() .
                        " with error on " . $job->server(), 1);

                // Clean up server
                $this->cleanUpFileServer($job);

                // Reset server and remove job from the job queue
                // (update database)
                $this->stopTime = $queue->stopJob($job);

                // Write email
                if ($send_mail) {
		  $this->notifyError($job, $startTime);
                }

                if (file_exists($errorFile)) {
                    $gFile = $logdir . "/" . $job->server() . "_error.txt";

                    # Accumulate error logs
                    exec("cat \"$errorFile\" >> \"$gFile\"");
                    unlink($errorFile);
                }

                if (file_exists($logFile)) {
                    unlink($logFile);
                }
            }
            else {
                $this->chmodJob($desc, $fileserver);

                report("job " . $desc->id() . " completed on " .
                    $job->server(), 1);

                // Report information to statistics table
                $db = new DatabaseConnection();
                $db->updateStatistics($job, $startTime);
                // Clean up server
                $this->cleanUpFileServer($job);
                // Reset server and remove job from the job queue
                $this->stopTime = $queue->stopJob($job);
                $this->assembleJobLogFile($job, $startTime, $logFile, $errorFile);

                // Write email
                if ($send_mail)
                    $this->notifySuccess($job, $startTime);
                if (file_exists($errorFile)) {
                    unlink($errorFile);
                }
                if (file_exists($logFile))
                    unlink($logFile);
            }
        }
    }

    /*!
 	 \brief	Assembles the job log to be displayed in the file manager.

 	 The job log contains the HuCore output and error log and some additional
     information.

 	 \param	$job		A Job object
 	 \param	$startTime	Start time of the Job
 	 \param	$logFile	Full path to the log file
 	 \param	$errorFile	Full path to the errorlog file
 	 */
 	public function assembleJobLogFile($job, $startTime, $logFile, $errorFile) {
        global $imageProcessingIsOnQueueManager;
        $result = False;
        $desc = $job->description();
        $imageName = $desc->destinationImageName();
        $id = $desc->id();
        $pid = $job->pid();
        $server = $job->server();
        $user = $desc->owner();
        $username = $user->name();
        $fileserver = new Fileserver($username);
        $path = $fileserver->destinationFolderFor($desc);
        $path = str_replace(" ", "_", $path); // HuCore path naming standard.

        // Message
        $text = '';
        $text .= "Job id: $id (pid $pid on $server), started " .
            "at $startTime and finished at " . date("Y-m-d H:i:s") . "\n\n";

        if (file_exists($errorFile))
            $text .= "- HUYGENS ERROR REPORT (stderr) --------------\n\n" .
                    file_get_contents($errorFile);
        if (file_exists($logFile))
            $text .= "- HUYGENS REPORT (stdout) --------------------\n\n" .
                    file_get_contents($logFile);

        // Save the log to file
        $parameterFileName = $path . $imageName . '.log.txt';
        $file = fopen($parameterFileName, "w");
        $result = !$result && (fwrite($file, $text) > 0);
        fclose($file);

        if (!$imageProcessingIsOnQueueManager) {
            $this->restoreOwnership($username);
        }

        return $result;
    }

    /*!
 	\brief	Sends an e-mail to the User notifying a successful Job
 	\param	$job		A Job object
    \param	$startTime	Start time of the Job
    */
 	public function notifySuccess($job, $startTime) {
        global $email_sender;
        global $hrm_url;
        global $useThumbnails;
        $desc = $job->description();
        $user = $desc->owner();
        $emailAddress = $user->emailAddress();
        $sourceFileName = $desc->sourceImageNameWithoutPath();
        $destFileName = $desc->destinationImageNameWithoutPath();
        $text = "This is a mail generated automatically by " .
            "the Huygens Remote Manager.\n\n";
        $text .= "The image $sourceFileName was successfully " .
            "processed by Huygens.\n";
        $text .= "Your job started at $startTime and finished at " .
            date("Y-m-d H:i:s") . ".\n";
        $text .= "You will find the resulting image ($destFileName) " .
            "in your destination folder.\n";

        // Create link based on the job id.
        $imageName = $desc->destinationImageNameAndExtension();
        $namePattern = "/(.*)_(.*)_(.*)\.(.*)$/";
        preg_match($namePattern, $imageName, $matches);
        $jobName = str_replace($matches[1] . "_", "", $imageName);

        // Stick to the HuCore path naming standard.
        $dirname = str_replace(" ", "_", dirname($imageName));
        $linkName = $dirname . "/" . $jobName;

        if ($useThumbnails) {
            $link = $hrm_url . "/file_management.php?compareResult=" .
                    urlencode($linkName);
            $text .= "\nDirect link to your deconvolution result: " . $link .
                    " (login required).\n\n";
        }
        $text .= "Best regards,\nHuygens Remote Manager\n\n";

        // Send an email
        $mail = new Mail($email_sender);
        $mail->setReceiver($emailAddress);
        $mail->setSubject('Your HRM job finished successfully');
        $mail->setMessage($text);
        $mail->send();
    }

    /*!
    \brief	Sends an e-mail to the User and Admin notifying a failed Job
    \param	$job		A Job object
    \param	$startTime	Start time of the Job
    */
 	public function notifyError($job, $startTime) {
	  global $email_sender;
	  global $email_admin;
	  global $logdir;


	  /* Definitions: relevant files. */
	  $basename  = $logdir . "/" . $job->server() . "_" . $job->id();
	  $errorFile = $basename . "_error.txt";
	  $logFile   = $basename . "_out.txt";

	  /* Definitions: dataset name. */
	  $desc = $job->description();
	  $sourceFileName = $desc->sourceImageNameWithoutPath();

	  // Definitions: job id, pid, server. */
	  $id     = $desc->id();
	  $pid    = $job->pid();
	  $server = $job->server();
	  $template = $job->createHuygensTemplate();

	  /* Email destination. */
	  $user = $desc->owner();
	  $emailAddress = $user->emailAddress();


	  $mailContent  = "\nThis is a mail generated automatically by ";
	  $mailContent .= "the Huygens Remote Manager.\n\n";

	  $mailContent .= "Sorry, the processing of the image \n";
	  $mailContent .= $sourceFileName . "\nhas been terminated with ";
	  $mailContent .= "an error.\n\n";

	  $mailContent .= "Best regards,\nHuygens Remote Manager\n";

	  /* The error should be shown up in the email. */
	  if (file_exists($errorFile)) {
	    $mailContent .= "\n\n-HUYGENS ERROR REPORT (stderr) --------------";
	    $mailContent .= "\n\n" . file_get_contents($errorFile);
	  }

	  $mailContent .= "\n\n- USER PARAMETERS -----------------------------";
	  $mailContent .= "------\n\n";
	  $mailContent .= "These are the parameters you set in the HRM:\n\n";
	  $mailContent .= $this->parameterText($job);

	  $mailContent .= "\n\n-TEMPLATE -------------------------------------";
	  $mailContent .= "------\n\n";
	  $mailContent .= "What follows is the Huygens Core template executed ";
	  $mailContent .= "when the error occured:\n\n";
	  $mailContent .= $job->getHuTemplate();
	  $mailContent .= "\n\n-----------------------------------------------";
	  $mailContent .= "------\n\n";

	  if (file_exists($logFile)) {
	    $mailContent .= "\n\n-HUYGENS REPORT (stdout) --------------------";
	    $mailContent .= "\n\n" . file_get_contents($logFile);
	  }

	  $mailContent .= "\n\n-PROCESS DETAILS-------------------------------";
	  $mailContent .= "------\n\n";

	  $mailContent .= "Your job started on $startTime and failed ";
	  $mailContent .= "on " . date("Y-m-d H:i:s") . ".\n";

	  $mailContent .= "Job id: $id (pid $pid on $server)\n";


	  /* Send the error mail to the user. */
	  $mail = new Mail($email_sender);
	  $mail->setReceiver($emailAddress);
	  $mail->setSubject('Your HRM job finished with an error');
	  $mail->setMessage($mailContent);
	  $mail->send();

	  /* Also notify the error to the admin. */
	  $mail->setReceiver($email_admin);
	  $mail->setSubject('An HRM job from user "' . $user->name() .
			    '" finished with an error.');

	  $mail->send();
	}


    /*!
 	\brief	Sends an e-mail to the Admin notifying that a server could
            not be pinged
 	\param	$name	Name of the server that was (not) pinged
    */
 	public function notifyPingError($name) {
        global $email_sender;
        global $email_admin;
        report("Ping error notification sent", 1);
        $text = "Huygens Remote Manager warning:\n"
                . $name . " could not be pinged on " . date("r", time());
        $mail = new Mail($email_sender);
        $mail->setReceiver($email_admin);
        $mail->setSubject('Huygens Remote Manager - ping error');
        $mail->setMessage($text);
        $mail->send();
    }

    /*!
 	\brief  Report (and internally store) the name of a free server that
            can accept a Job
 	\return name of a free server
 	*/
 	public function getFreeServer() {

        $db = new DatabaseConnection();
        $servers = $db->availableServer();

        foreach ($servers as $server) {
            $status = $db->statusOfServer($server);
            if ($status == 'free') {

                if ( $this->isProcessingServerReachable($server) ) {
                    $this->nping[$server] = 0;
                    $this->freeServer = $server;
                    return True;
                } else {
                    $this->incNPing($server);
                    if ($this->nping[$server] == 40) {
                        $this->notifyPingError($server);
                    }
                }
            }
        }

        $this->freeServer = False;

        return $this->freeServer;
    }

    /*!
 	\brief	Inform the QueueManager that it should stop
 	*/
 	public function stop() {
        $this->shallStop = True;
    }

    /*!
 	\brief	Check (in the database) if the QueueManager shall stop
            (i.e. leave its main loop)
 	\return true if the QueueManager shall stop
    */
 	public function shallStop() {
            if ($this->shallStop) {
                return True;
            }
            $this->waitForDatabaseConnection();
            $db = new DatabaseConnection();
            $this->shallStop = !$db->isSwitchOn();
            return $this->shallStop;
        }

    /*!
 	\brief	Waits until a DatabaseConnection could be established
 	*/
 	public function waitForDatabaseConnection() {
        $isDatabaseReachable = False;
        while (!$isDatabaseReachable) {
            $db = new DatabaseConnection();
            if ($db->isReachable()) {
                $isDatabaseReachable = True;
            }
        }
    }

    /*!
 	\brief	Runs the QueueManager
 	*/
 	public function run() {
        global $imageProcessingIsOnQueueManager;
        global $logdir;

        $queue = $this->queue;

        // Make sure that we have access to the database
        $this->waitForDatabaseConnection();

        $server = $queue->availableServer();
        // TODO refactor this in order to manage error logs per job
        foreach ($server as $name) {
            $this->nping[$name] = 0;
        }

        report("Huygens Remote Manager started on "
                . date("Y-m-d H:i:s") . "\n", 1);

        if (!$this->askHuCoreVersionAndStoreIntoDB()) {
            error_log("An error occurred while reading HuCore version");
            return;
        }

        if (!$this->storeHuCoreLicenseDetailsIntoDB()) {
            error_log("An error occurred while saving HuCore license details");
            return;
        }

        if (!$this->storeConfidenceLevelsIntoDB()) {
            error_log("An error occurred while storing the confidence " .
                "levels in the database");
            return;
        }

        while (!$this->shallStop()) {
            set_time_limit(0);
            $this->queue = $queue;
            $result = True;

            // Reduce the used cycles by going to sleep for one second
            if ($imageProcessingIsOnQueueManager) {
                sleep(1);
            }

            // Check if jobs finished and update the database. Inform the
            // user via email.
            $this->updateJobAndServerStatus();

            // Read in a free huygens server
            while (!( $queue->isLocked() ) && $this->getFreeServer()) {

                $job = $this->nextJobFromQueue();
                // Exit the loop if no job is queued.
                if ($job == NULL) {
                    break;
                }

                report("using Huygens server: " . $this->freeServer, 2);

                // Read in a queued job
                $desc = $job->description();
                $id = $desc->id();
                report("processing job " . $id . " on " . $job->server(), 1);

                // TODO check this <<
                // If the job is compound create sub jobs and
                // remove job otherwise create template
                $result = $job->createSubJobsOrHuTemplate();
                if (!$result || $desc->isCompound()) {
                    error_log("error or compound job");
                    continue;
                }
                report("template has been created", 1);

                // Execute the template on the Huygens server and
                // update the database state
                $result = $result && $this->executeTemplate($job);

                if (!$result) {
                    continue;
                }

                report("Template has been executed", 1);
                $result = $result && $queue->startJob($job);
                report("job has been started ("
                        . date("Y-m-d H:i:s") . ")", 1);
            }
        }
        report("Huygens Remote Manager stopped via database switch on "
                . date("Y-m-d H:i:s"), 1);
    }

    /*
        PRIVATE FUNCTIONS
 	*/

    /*!
 	\brief	Prepares the text with the summary of the Job parameters to be sent
            to the user
 	\param	$job		A Job object
 	\return	the text to be later sent by email
    */
 	private function parameterText(Job $job) {
        $desc = $job->description();
        $result = '';

        $result = $result . "\nImage parameters:\n\n";
        $parameterSetting = $desc->parameterSetting();
        $parameterSettingString = $parameterSetting->displayString();
        $result = $result . $parameterSettingString;

        $result = $result . "\nRestoration parameters:\n\n";
        $taskSetting = $desc->taskSetting();
        $numberOfChannels = $taskSetting->numberOfChannels();
        $taskSettingString = $taskSetting->displayString($numberOfChannels);
        $result = $result . $taskSettingString;

        $result = $result . "\nAnalysis parameters:\n\n";
        $analysisSetting = $desc->analysisSetting();
        $analysisSettingString = $analysisSetting->displayString();
        $result = $result . $analysisSettingString;

        return $result;
    }

    /*!
 	\brief	Increment the number of attempts of pining a server with given name
 	\param	$name 	Name of the server
 	*/
 	private function incNPing($name) {
        $this->nping[$name]++;
    }

    /*!
 	\brief	Asks HuCore to provide its version number and store it in the DB
 	\return true if asking the version and storing it in the database was
            successful, false otherwise
    */
 	private function askHuCoreVersionAndStoreIntoDB() {
            $huversion = askHuCore("reportVersionNumberAsInteger");
            $huversion = $huversion["version"];
            report("HuCore version = " . $huversion . "\n", 2);
            if (!System::setHuCoreVersion($huversion)) {
                return false;
            }
            return true;
        }

        /*!
         \brief   Gets license details from HuCore and saves them into the db.
         \return  Boolean: true if everything went OK.
        */
        private function storeHuCoreLicenseDetailsIntoDB( ) {
            $licDetails = askHuCore("reportHuCoreLicense");

            // Store the license details in the database.
            $db = new DatabaseConnection();
            if (!$db->storeLicenseDetails($licDetails['license'])) {
                report("Could not store license details in the database!\n", 1);
                return false;
            }

            return true;
        }


    /*!
 	\brief	Store the confidence levels returned by huCore into the database
            for faster retrieval
 	\return true if asking the version and storing it in the database was
            successful, false otherwise
 	*/
	private function storeConfidenceLevelsIntoDB() {

        // Get the confidence levels string from HuCore
        $result = askHuCore("reportFormatInfo");
        $confidenceLevelString = $result["formatInfo"];

        // Parse the confidence levels string
        $confidenceLevels =
            $this->parseConfidenceLevelString($confidenceLevelString);

        // Store the confidence levels in the database
        $db = new DatabaseConnection();
        if (!$db->storeConfidenceLevels($confidenceLevels)) {
            report("Could not store confidence levels to the database!\n", 1);
            return false;
        }
        return true;
    }

    /*!
    \brief	Parse the string returned by HuCore containing the confidence
            levels for parameters and return them in an array
    \return an array containing the parameter confidence levels
 	*/
	private function parseConfidenceLevelString($confidenceLevelString) {

        // Break down the string into per-file substrings
        $confidenceLevelString = str_replace('}}', '}}<CUT_HERE>',
            $confidenceLevelString);

        $groups = explode('<CUT_HERE> ', $confidenceLevelString);

        // Prepare the output array
        $confidenceLevels = array();

        // Confidence level regexp
        $levelRegExp = '(asIs|reported|estimated|default|verified)';

        // Process the substrings
        foreach ($groups as $group) {

            $match = array();
            preg_match("/(\A\w{2,16})(\s{1,2})(\{sampleSizes\s\{.+)/",
                $group, $match);

            // Get the parts
            if ((!isset($match[1]) ) || (!isset($match[3]) )) {
                $msg = "Could not parse confidence levels!";
                report($msg, 1);
                exit($msg);
            }
            $fileFormat = $match[1];
            $parameters = $match[3];

            // Prepare the parameter array
            $params = array(
                "fileFormat" 	 => "none",
                "sampleSizesX"	 => "default",
                "sampleSizesY"	 => "default",
                "sampleSizesZ"	 => "default",
                "sampleSizesT"	 => "default",
                "iFacePrim" 	 => "default",
                "iFaceScnd" 	 => "default",
                "pinhole"        => "default",
                "chanCnt"        => "default",
                "imagingDir" 	 => "default",
                "pinholeSpacing" => "default",
                "objQuality" 	 => "default",
                "lambdaEx" 	     => "default",
                "lambdaEm"       => "default",
                "mType"          => "default",
                "NA"             => "default",
                "RIMedia" 	     => "default",
                "RILens"         => "default",
                "photonCnt" 	 => "default",
                "exBeamFill" 	 => "default",
                "stedMode"       => "default",
                "stedLambda"     => "default",
                "stedSatFact"    => "default",
                "stedImmunity"   => "default",
                "sted3D"         => "default");

            // To enable parsing of sted parameters in a loop.
            $stedParams = array("stedMode",
                                "stedLambda",
                                "stedSatFact",
                                "stedImmunity",
                                "sted3D");

            // Store the file format
            $params['fileFormat'] = $fileFormat;

            // Parse the parameters
            // Sample sizes
            $exp = "/\{sampleSizes\s\{" . $levelRegExp . "\s" . $levelRegExp .
                    "\s" . $levelRegExp . "\s" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['sampleSizesX'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesX!";
                report($msg, 1);
                exit($msg);
            }
            if (isset($match[2])) {
                $params['sampleSizesY'] = $match[2];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesY!";
                report($msg, 1);
                exit($msg);
            }
            if (isset($match[3])) {
                $params['sampleSizesZ'] = $match[3];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesZ!";
                report($msg, 1);
                exit($msg);
            }
            if (isset($match[4])) {
                $params['sampleSizesT'] = $match[4];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesT!";
                report($msg, 1);
                exit($msg);
            }

            // iFacePrim
            $exp = "/iFacePrim\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['iFacePrim'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter iFacePrim!";
                report($msg, 1);
                exit($msg);
            }

            // iFaceScnd
            $exp = "/iFaceScnd\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['iFaceScnd'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter iFaceScnd!";
                report($msg, 1);
                exit($msg);
            }

            // pinhole
            $exp = "/pinhole\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['pinhole'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter pinhole!";
                report($msg, 1);
                exit($msg);
            }

            // chanCnt
            $exp = "/chanCnt\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['chanCnt'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter chanCnt!";
                report($msg, 1);
                exit($msg);
            }

            // imagingDir
            $exp = "/imagingDir\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['imagingDir'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter imagingDir!";
                report($msg, 1);
                exit($msg);
            }

            // pinholeSpacing
            $exp = "/pinholeSpacing\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['pinholeSpacing'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter pinholeSpacing!";
                report($msg, 1);
                exit($msg);
            }

            // objQuality
            $exp = "/objQuality\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['objQuality'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter objQuality!";
                report($msg, 1);
                exit($msg);
            }

            // lambdaEx
            $exp = "/lambdaEx\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['lambdaEx'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter lambdaEx\!";
                report($msg, 1);
                exit($msg);
            }

            // lambdaEm
            $exp = "/lambdaEm\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['lambdaEm'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter lambdaEm\!";
                report($msg, 1);
                exit($msg);
            }

            // mType
            $exp = "/mType\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['mType'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter mType\!";
                report($msg, 1);
                exit($msg);
            }

            // NA
            $exp = "/NA\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['NA'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter NA\!";
                report($msg, 1);
                exit($msg);
            }

            // RIMedia
            $exp = "/RIMedia\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['RIMedia'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter RIMedia\!";
                report($msg, 1);
                exit($msg);
            }

            // RILens
            $exp = "/RILens\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['RILens'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter RILens\!";
                report($msg, 1);
                exit($msg);
            }

            // photonCnt
            $exp = "/photonCnt\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['photonCnt'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter photonCnt\!";
                report($msg, 1);
                exit($msg);
            }

            // exBeamFill
            $exp = "/exBeamFill\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['exBeamFill'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter exBeamFill\!";
                report($msg, 1);
                exit($msg);
            }

            // sted parameters
            foreach ($stedParams as $stedParam) {
                $exp = "/$stedParam\s\{" . $levelRegExp . "\}/";
                $match = array();
                preg_match($exp, $parameters, $match);
                if (isset($match[1])) {
                    $params[$stedParam] = $match[1];
                } else {
                    $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter $stedParam\!";
                    report($msg, 1);
                    exit($msg);
                }
            }

            // Store the parameters for current file format
            $confidenceLevels[$fileFormat] = $params;
        }

        return $confidenceLevels;
    }

}
?>
