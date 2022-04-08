<?php
/**
 * QueueManager
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

use hrm\job\Job;
use hrm\job\JobDescription;
use hrm\job\JobQueue;
use hrm\shell\ExternalProcessFactory;
use hrm\user\UserConstants;
use hrm\user\UserV2;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Creates Jobs from JobDescriptions and manages them in a priority queue.
 *
 * @package hrm
 */
class QueueManager
{

    /**
     * A JobQueue object.
     * @var JobQueue
     */
    private $queue;

    /**
     * Name of a free server.
     * @var string
     */
    private $freeServer;

    /**
     * Name of a free GPU card at server 'freeServer'.
     * @var string
     */
    private $freeGpu;

    /**
     * Flag to indicate whether a Job should stop.
     * @var bool
     */
    private $shallStop;

    /**
     * Time of completion of a Job.
     * @var string
     */
    private $stopTime;

    /**
     * Number of times each server has been pinged.
     * @var array
     */
    private $nping;

    /**
     * List of running Jobs.
     * @var array
     */
    private $runningJobs;

    /**
     * Number of seconds since the Unix Epoch until the last
     * time the 'onceAnHourLog()' function logged the status
     * of the queue.
     * @var int
     */
    private $onceAnHourLastTimeLog;


    /**
     * QueueManager constructor.
     */
    public function __construct()
    {
        $this->runningJobs = array();
        $this->queue = new JobQueue();
        $this->shallStop = false;
        $this->nping = array();
    }

    /**
     * If existing, delete debris from previously run jobs.
     * @param JobDescription $desc A JobDescription object.
     * @param string $server_hostname The server where the job will be run.
     * @todo Move to Shell.php.
     */
    public function removeHuygensOutputFiles(JobDescription $desc, $server_hostname)
    {
        global $imageProcessingIsOnQueueManager;
        global $huygens_user;

        // Get the Huygens default output file.
        $user = $desc->owner();
        $fileserver = new Fileserver($user->name());
        $destPath = $fileserver->destinationFolderFor($desc);
        $huyOutFile = $destPath . "scheduler_client0.log";

        // Build a remove command involving the file.
        $cmd = "if [ -f \"" . $huyOutFile . "\" ]; ";
        $cmd .= "then ";
        $cmd .= "rm \"" . $huyOutFile . "\"; ";
        $cmd .= "fi";

        if ($imageProcessingIsOnQueueManager) {
            exec($cmd);
        } else {
            $cmd = "'$cmd'";
            exec("ssh " . $huygens_user . "@" . $server_hostname . " " . $cmd);
        }
    }

    /**
     * Executes given Job.
     * @param $job Job A Job object.
     * @return true if the Job could be executed, false otherwise.
     * @todo  Update templateName variable with templateName
     */
    public function executeTemplate(Job $job)
    {
        global $imageProcessingIsOnQueueManager;
        global $copy_images_to_huygens_server;

        $server = $this->freeServer;
        // server name without proc number
        $s = explode(" ", $server);
        $server_hostname = $s[0];
        $desc = $job->description();
        $clientTemplatePath = $desc->sourceFolder();
        $templateName = $job->huTemplateName();

        // The new job must not get merged with debris from previously
        // failed jobs.
        $this->removeHuygensOutputFiles($desc, $server_hostname);

        Log::info("Executing template for job with id " .
            $job->id() . ": " .
            $imageProcessingIsOnQueueManager . " " .
            $copy_images_to_huygens_server);

        if (!$imageProcessingIsOnQueueManager &&  $copy_images_to_huygens_server) {
            $clientTemplatePath = $this->copyImagesToServer($job, $server_hostname);
            Log::info("images copied to IP server");
        }

        $proc = ExternalProcessFactory::getExternalProcess(
            $server,
            $server . "_" . $job->id() . "_out.txt",
            $server . "_" . $job->id() . "_error.txt"
        );
        Log::info("Shell process created for job with id " . $job->id());

        /* Check whether the shell is ready to accept further execution. If
         not, the shell will be released internally, no need to release it
         here. */
        if (!$proc->runShell()) {
            return false;
        }

        Log::info("Running shell: $clientTemplatePath$templateName");
        $pid = $proc->runHuygensTemplate($clientTemplatePath . $templateName);

        Log::info("Running template (pid $pid)");

        /* The template in the background will keep running after release. */
        $proc->release();

        $job->setPid($pid);
        $job->setServer($server);
        return ($pid > 0);
    }

    /**
     * Copies the images needed by a given Job to the processing server.
     * @param Job $job A Job object.
     * @param string $server_hostname Name of the server to which to copy the files.
     * @return string The full path to which the files were copied.
     */
    public function copyImagesToServer(Job $job, $server_hostname)
    {
        global $huygens_user;
        global $image_folder;
        global $image_source;
        global $image_destination;
        global $huygens_server_image_folder;

        Log::info("Copying images to IP server");

        $desc = $job->description();
        $user = $desc->owner();

        // @TODO substitute spaces by underscores in image name to avoid
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

                /* This loop goes over the max number of channels, even if
		they are empty. To prevent sftp errors those channels have to
		be skipped. */
                if ($value == "") continue;
	        
                $path = explode("/", $value);
                if (sizeof($path) > 0) {
                    for ($i = 0; $i < sizeof($path) - 1; $i++) {
                        $batch .= "-mkdir \"" . $path[$i] . "\"\n";
                        $batch .= "cd \"" . $path[$i] . "\"\n";
                    }
                }
                $filename = $image_folder . "/" . $user->name() . "/" .
                    $image_source . "/" . $value;
                if (stristr($filename, ".ics")) {
                    $batch .= "put \"" . $filename . "\"\n";
                    $filename = preg_replace("/.ics/", ".ids", $filename);
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
        $filteredFiles = array();
        $counter = -1;
        foreach ($files as $file) {
            $counter++;
            $match = array();
            if (preg_match("/^(.*\.(lif|lof|czi|nd))\s\((.*)\)/i", $file, $match)) {
                $filteredFiles[$counter] = $match[1];
            } else {
                $filteredFiles[$counter] = $file;
            }
        }
        $files = array_unique($filteredFiles);

        // Now copy the files
        foreach ($files as $file) {
            $path = explode("/", $file);
            if (sizeof($path) > 0) {
                for ($i = 0; $i < sizeof($path) - 1; $i++) {
                    $batch .= "-mkdir \"" . $path[$i] . "\"\n";
                    $batch .= "cd \"" . $path[$i] . "\"\n";
                }
            }
            $filename = $image_folder . "/" . $user->name() . "/" .
                $image_source . "/" . $file;
            if (stristr($filename, ".ics")) {
                $batch .= "put \"" . $filename . "\"\n";

		// Get the 'c' from 'ics' in order to find its case.
                $nameLength    = strlen($filename);
                $extensionChar = $filename[$nameLength - 2];

                if (ctype_upper($extensionChar)) {
                    $extensionChar = "D";
                } else {
                    $extensionChar = "d";
                }

                $filename[$nameLength - 2] = $extensionChar;

                $batch .= "put \"" . $filename . "\"\n";
            } elseif (stristr($filename, ".tif") || stristr($filename, ".tiff")) {
                // TODO: if ImageFileFormat = single TIFF file, do not send
                // corresponding series
                $basename = preg_replace(
                    "/([^_]+|\/)(_)(T|t|Z|z|CH|ch)([0-9]+)(\w+)(\.)(\w+)/",
                    "$1$6$7",
                    $filename
                );
                $name = preg_replace("/(.*)\.tiff?$/", "$1", $basename);
                $batch .= "put \"" . $name . "\"*\n";
            } elseif (stristr($filename, ".stk")) {
                // if ImageFileFormat = STK time series, send all timepoints
                if (stripos($filename, "_t")) {
                    $basename = preg_replace(
                        "/([^_]+|\/)(_)(T|t)([0-9]+)(\.)(\w+)/",
                        "$1",
                        $filename
                    );
                    $batch .= "put \"" . $basename . "\"*\n";
                } else {
                    $batch .= "put \"" . $filename . "\"\n";
                }
            } elseif (stristr($filename, ".nd")) {
                $basename = preg_replace(
                    "/([^_]+|\/)(_)(T|t|Z|z|CH|ch)([0-9]+)(\w+)(\.)(\w+)/",
                    "$1$6$7",
                    $filename
                );
                $name = preg_replace("/(.*)\.nd?$/", "$1", $basename);
                $batch .= "put \"" . $name . "\"*\n";
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

        Log::info("BATCH");
        Log::info("$batch");

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


        return $huygens_server_image_folder . "/" . $user->name() . "/" .
        $image_source . "/";
    }

    /**
     * Returns the next Job in the queue.
     * @return Job the next Job in the queue.
     */
    public function nextJobFromQueue()
    {
        $queue = $this->queue;
        $foundExecutableJob = false;
        $pausedJobs = false;
        $jobDescription = $queue->getNextJobDescription();
        while ($jobDescription != null && !$foundExecutableJob) {
            $user = $jobDescription->owner();
            $username = $user->name();
            $fileserver = new Fileserver($username);
            if ($fileserver->isReachable()) {
                $foundExecutableJob = true;
            } else {
                $src = $fileserver->sourceFolder();
                $dest = $fileserver->destinationFolder();
                Log::error("fileserver not reachable: $src or $dest" . " do not exist");
                $pausedJobs = true;
                $queue->pauseJob($jobDescription);
                return null;
            }
            $jobDescription = $queue->getNextJobDescription();
        }
        if ($pausedJobs) {
            $queue->restartPausedJobs();
        }
        if ($jobDescription == null) {
            return null;
        }
        $job = new Job($jobDescription);
        $job->setServer($this->freeServer);
        $job->setGPU($this->freeGpu);
        return $job;
    }


    /**
     * Prints the status of the queue to the HRM log.
     * This can be useful to spot problems in stalled queues.
     */
    public function onceAnHourLog()
    {
        // Check that an hour went by since the last status log.
        // Otherwise return, we don't want to clog the log.
        if (isset($this->onceAnHourLastTimeLog)
            && $this->onceAnHourLastTimeLog - time() < 3600) {
            return;
        }

        $queue      = $this->queue;
        $isLocked   = $queue->isLocked();
        $freeServer = $this->getFreeServer();

        $db         = DatabaseConnection::get();
        $nextJobId  = $db->getNextIdFromQueue();

        $this->onceAnHourLastTimeLog = time();

        $msg = "Queue status -> locked: " ;
        if ($isLocked === True) {
            $msg .= "true";
        } else {
            $msg .= "false";
        }

        $msg .= ", free server: ";
        if ($freeServer === True) {
            $msg .= "true";
        } else {
            $msg .= "false";
        }

        $msg .= ", nextJobId: ";
        if (isset($nextJobId[0])) {
            $msg .= $nextJobId[0];
        } else {
            $msg .= "null";
        }

        Log::info($msg);
    }


    /**
     * Deletes temporary Job files from the file server
     *
     * @TODO If the processing server is not local, the cleaning will have no effect!
     * @param Job $job A Job object.
     */
    private function cleanUpFileServer($job)
    {
        Log::warning("Cleaning up file server");
        $server = $job->server();
        // server name without proc number
        $s = explode(" ", $server);
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
            Log::warning("removing finished marker");
            unlink($finishedMarker);
        }
        $endTimeMarker = $path . '/' . '.EstimatedEndTime_' . "$id";
        if (file_exists($endTimeMarker)) {
            Log::warning("removing EstimatedEndTime report");
            unlink($endTimeMarker);
        }
        // remove job
        $this->stopTime = $queue->stopJob($job);
        Log::info("Completed and cleaned job with id " . $job->id() . " (" . date("l d F Y H:i:s") . ")\n");
    }


    /**
     * Deletes the job source and destination files from the remote server.
     *
     * @param Job $job A Job object.
     */
    public function cleanUpRemoteServer($job)
    {
        global $imageProcessingIsOnQueueManager;
        global $copy_images_to_huygens_server;
        global $huygens_server_image_folder;
        global $image_source;
        global $image_destination;


        if ($imageProcessingIsOnQueueManager || !$copy_images_to_huygens_server) {
            // There's no remote server. Nothing to do.
            return;
        }

        // Hostname == registered server entry without GPU tag.
        $jobServer = $job->server();
        $s = explode(" ", $jobServer);
        $jobHostname = $s[0];

        // Other job properties.
        $jobDescription = $job->description();
        $jobOwner       = $jobDescription->owner();

        // Sanity check just in case something goes wrong with the configuration.
        // Make sure we are dealing with a remote server. If the remote server
        // is equal to the machine running the QM, then there must have been a
        // mistake.
        $qmHostname = gethostname();
        if (strcasecmp($jobHostname, $qmHostname) == 0) {
            return;
        }

        // It should be safe to remove the data now. Additionally, this operation is
        // "protected" by the SSH keys. Only if HRM has been configured correctly, will
        // the file deletion take place.
        $proc = ExternalProcessFactory::getExternalProcess(
            $jobServer,
            $jobServer . "_remote_cleanup_out.txt",
            $jobServer . "_remote_cleanup_error.txt"
        );
        Log::info("Shell process created");

        // Check whether the shell is ready to accept further execution. If not,
        // the shell will be released internally, no need to release it here.
        if (!$proc->runShell()) {
            return;
        }

        // Clean up the job dest images at the remote location.
        $remotePath  = $huygens_server_image_folder . "/";
        $remotePath .= $jobOwner->name() . "/" . $image_destination . "/";
        $remoteFiles = "*" . $job->id() . "*";

        $cmd = "find " . $remotePath . " -name '" . $remoteFiles . "' -delete;";
        $proc->execute($cmd);

        // Unfortunately, the DB does not keep track of all the source files used
        // by the job. This is because the file series can become very large.
        // Thus, we only retrieve the name of the job's main source file.
        // Additionally, we check if there are any other jobs from the same user
        // running on the same machine with the same source files. If so, we skip
        // the cleanup and leave the task to the last job that meets such criteria.
        // With this, we are able to properly clean up 99.9% of the cases. There
        // might be some extreme cases of file collisions or debris from previous
        // old jobs (HRM < 3.7 did not clean them up) where this still fails.
        $srcFiles = $jobDescription->files();

        // Loop over all the running jobs to find out about collisions.
        $runningJobs = $this->queue->runningJobs();
        foreach ($runningJobs as $runningJob) {
            // Hostname == registered server entry without GPU tag.
            $runningJobServer = $runningJob->server();
            $s = explode(" ", $runningJobServer);
            $runningJobHostname = $s[0];

            // Other properties of the running job.

            /** @var JobDescription $runningJobDescription */
            $runningJobDescription = $runningJob->description();

            /** @var UserV2 $runningJobOwner */
            $runningJobOwner = $runningJobDescription->owner();

            if ($runningJob->id() == $job->id()) {
                continue;
            }
            if ($runningJobOwner->name() != $jobOwner->name()) {
                continue;
            }
            if ($runningJobHostname != $jobHostname) {
                continue;
            }

            // Remove collisions with other jobs from the list of source files.
            $runningJobSrcFiles = $runningJobDescription->files();
            $srcFiles = array_diff($srcFiles, $runningJobSrcFiles);
        }

        // Lastly, clean up the job source images at the remote location.
        $cmd = "";
        $fileServer = new Fileserver($jobOwner->name());
        $fileSeries = $jobDescription->autoseries();
        foreach ($srcFiles as $srcFile) {
            $remotePath  = $huygens_server_image_folder . "/";
            $remotePath .= $jobOwner->name() . "/" . $image_source . "/";

	    $extension = $fileServer->getFileNameExtension($srcFile);
	    $srcFile   = $fileServer->basename($srcFile);
            $srcFile   = str_replace($srcFile, $extension, "");

            // If we are dealing with a series, remove all of its files.
            if ($fileSeries) {
                $srcFile .= "*" . $extension;
            } elseif ($extension == "ics") {
	        $srcFile .= ".i*s"; 
            }
	    
            $remoteFiles = $srcFile;

            $cmd .= "find " . $remotePath . " -name '" . $remoteFiles . "' -delete;";
        }

        if ($cmd != "") {
            $proc->execute($cmd);
        }

        $proc->release();
    }


    /**
     * Checks whether the processing server reacts on 'ping'.
     * @param string $server The server's name.
     * @param string $outLog A string specific of the output log file name.
     * @param string $errLog A string specific of the error log file name.
     * @return bool True on success, false otherwise.
     */
    private function isProcessingServerReachable($server, $outLog = null, $errLog = null)
    {
        if ($outLog) {
            $outLog .= "_";
        }

        if ($errLog) {
            $errLog .= "_";
        }

        $proc = ExternalProcessFactory::getExternalProcess(
            $server,
            $server . $outLog . "_out.txt",
            $server . $errLog . "_error.txt"
        );
        $isReachable = $proc->ping();

        $proc->release();

        return $isReachable;
    }

    /**
     * Checks whether the processing server has enough free memory according
     * to the limits set in the configuration files.
     * @param string $server The server's name.
     * @param string $outLog A string specific of the output log file name.
     * @param string $errLog A string specific of the error log file name.
     * @return bool True on enough memory, false otherwise.
     */
    private function hasProcessingServerEnoughFreeMem($server, $outLog = null, $errLog = null)
    {
        global $min_free_mem_launch_requirement;

        // Initialize.
        $hasEnoughFreeMem = true;

        // Sanity checks.
        if (!isset($min_free_mem_launch_requirement) || !is_numeric($min_free_mem_launch_requirement)) {
            $min_free_mem_launch_requirement = 0;
        }
        if ($outLog) {
            $outLog .= "_";
        }
        if ($errLog) {
            $errLog .= "_";
        }

        $proc = ExternalProcessFactory::getExternalProcess(
            $server,
            $server . $outLog . "_out.txt",
            $server . $errLog . "_error.txt"
        );

        $isReachable = $proc->ping();

        if ($isReachable) {
            $freeMem = $proc->getFreeMem();
            if (is_numeric($freeMem) && $freeMem > 0 && $freeMem < $min_free_mem_launch_requirement) {
                $hasEnoughFreeMem = false;
            }
        }

        $proc->release();

        return $hasEnoughFreeMem;
    }

    /**
     * Updates the Job and server status.
     *
     * This methods kills all Jobs that are marked to be killed, checks whether
     * Jobs are completed and create report files, write Parameter files,...
     */
    public function updateJobAndServerStatus()
    {
        global $send_mail;
        global $logdir;

        // Alias
        $queue = $this->queue;

        // First remove all 'delete' jobs that haven't started yet.
        // This way we clean the queue as fast as possible.
        if (! $queue->removeMarkedJobs()) {
            // Report
            Log::error("Failed removing deleted, not yet started jobs from the queue.");

            // Force maintenance
            DatabaseConnection::get()->cleanQueueFromBrokenJobs();
        }

        // Then we kill running jobs that have been marked 'kill
        if (! $queue->killMarkedJobs()) {
            // Report
            Log::error("Failed killing running jobs from the queue.");

            // Force maintenance
            DatabaseConnection::get()->cleanQueueFromBrokenJobs();
        }

        // Now process the queue
        $runningJobs = $queue->runningJobs();
        if (count($runningJobs) > 0) {
            Log::info(count($runningJobs) . " job" .
                (count($runningJobs) == 1 ? " is" : "s are") . " running");
            // Because something is running, we are not in a hurry to continue.
            // Delay execution.
            sleep(5);
        }
        foreach ($runningJobs as $job) {
            /** @var Job $job */
            $desc = $job->description();
            /** @var JobDescription $desc */
            $user = $desc->owner();

            /** @var UserV2 $user */
            $fileserver = new Fileserver($user->name());
            if (!$fileserver->isReachable()) {
                Log::error("The file server is not reachable!");
                continue;
            }

            /** @var Job $job */
            if (!$this->isProcessingServerReachable($job->server(), $job->id(), $job->id())) {
                Log::error("The processing server is not reachable!");
                continue;
            }

            // Check finished marker
            if (! $job->checkProcessFinished()) {
                continue;
            }

            Log::info("Huygens process completed successfully.");

            // Check result image
            $resultSaved = $job->checkResultImage();

            // Notify user
            $startTime = $queue->startTime($job);
            $errorFile = $logdir . "/" . $job->server() . "_" . $job->id() . "_error.txt";
            $logFile = $logdir . "/" . $job->server() . "_" . $job->id() . "_out.txt";

            if (!$resultSaved) {
                Log::error("finishing job " . $desc->id() . " with error on " . $job->server());

                // Clean up server
                $this->cleanUpFileServer($job);

                // Reset server and remove job from the job queue (update database)
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
            } else {
                Log::info("Job " . $desc->id() . " completed on " . $job->server());

                // Report information to statistics table
                $db = DatabaseConnection::get();
                $db->updateStatistics($job, $startTime);

                // Clean up temporary job files in the file server.
                $this->cleanUpFileServer($job);

                // Remove all the transferred files from the processing server.
                $this->cleanUpRemoteServer($job);

                // Reset server and remove job from the job queue
                $this->stopTime = $queue->stopJob($job);
                $this->assembleJobLogFile($job, $startTime, $logFile, $errorFile);

                // Write email
                if ($send_mail) {
                    $this->notifySuccess($job, $startTime);
                }
                if (file_exists($errorFile)) {
                    unlink($errorFile);
                }
                if (file_exists($logFile)) {
                    unlink($logFile);
                }
            }
        }
    }

    /**
     * Assembles the job log to be displayed in the file manager.
     *
     * The job log contains the HuCore output and error log and some additional
     * information.
     *
     * @param Job $job A Job object.
     * @param string $startTime Start time of the Job.
     * @param string $logFile Full path to the log file.
     * @param string $errorFile Full path to the errorlog file.
     * @return string Job log to be displayed.
     */
    public function assembleJobLogFile($job, $startTime, $logFile, $errorFile)
    {
        $result = false;
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

        if (file_exists($errorFile)) {
            $text .= "- HUYGENS ERROR REPORT (stderr) --------------\n\n" . file_get_contents($errorFile);
        }
        if (file_exists($logFile)) {
            $text .= "- HUYGENS REPORT (stdout) --------------------\n\n" . file_get_contents($logFile);
        }

        // Save the log to file
        $parameterFileName = $path . $imageName . '.log.txt';
        $file = fopen($parameterFileName, "w");
        $result = !$result && (fwrite($file, $text) > 0);
        fclose($file);

        return $result;
    }

    /**
     * Sends an e-mail to the User notifying a successful Job
     * @param Job $job A Job object.
     * @param string $startTime Start time of the Job.
     */
    public function notifySuccess($job, $startTime)
    {
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

    /**
     * Sends an e-mail to the User and Admin notifying a failed Job.
     * @param Job $job A Job object
     * @param string $startTime Start time of the Job.
     */
    public function notifyError($job, $startTime)
    {
        global $email_sender;
        global $email_admin;
        global $logdir;


        /* Definitions: relevant files. */
        $basename = $logdir . "/" . $job->server() . "_" . $job->id();
        $errorFile = $basename . "_error.txt";
        $logFile = $basename . "_out.txt";

        /* Definitions: dataset name. */
        $desc = $job->description();
        $sourceFileName = $desc->sourceImageNameWithoutPath();

        // Definitions: job id, pid, server. */
        $id = $desc->id();
        $pid = $job->pid();
        $server = $job->server();
        // $job->createHuygensTemplate();

        /* Email destination. */
        $user = $desc->owner();
        $emailAddress = $user->emailAddress();


        $mailContent = "\nThis is a mail generated automatically by ";
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
        $mailContent .= "These are the parameters you set in HRM:\n\n";
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

    /**
     * Sends an e-mail to the Admin notifying that a server could
     * not be pinged.
     * @param string $name Name of the server that was (not) pinged.
     */
    public function notifyPingError($name)
    {
        global $email_sender;
        global $email_admin;
        Log::info("Ping error notification sent");
        $text = "Huygens Remote Manager warning:\n"
            . $name . " could not be pinged on " . date("r", time());
        $mail = new Mail($email_sender);
        $mail->setReceiver($email_admin);
        $mail->setSubject('Huygens Remote Manager - ping error');
        $mail->setMessage($text);
        $mail->send();
    }


    /**
     * Report (and internally store) the name of a free server that
     * can accept a Job.
     * @return string Name of a free server.
     */
    public function getFreeServer()
    {
        $db = DatabaseConnection::get();
        $servers = $db->availableServer();

        foreach ($servers as $server) {
            $status = $db->statusOfServer($server);
            if ($status == 'free') {
                if ($this->isProcessingServerReachable($server)) {
                    if ($this->hasProcessingServerEnoughFreeMem($server)) {
                        $this->nping[$server] = 0;
                        $this->freeServer = $server;
                        $this->freeGpu = $db->getGPUID($server);
                        return true;
                    }
                } else {
                    $this->incNPing($server);
                    if ($this->nping[$server] == 40) {
                        $this->notifyPingError($server);
                    }
                }
            }
        }

        $this->freeServer = false;

        return $this->freeServer;
    }

    /**
     * Inform the QueueManager that it should stop.
     */
    public function stop()
    {
        $this->shallStop = true;
    }

    /**
     * Check (in the database) if the QueueManager shall stop (i.e. leave its main loop)
     * @return bool True if the QueueManager shall stop.
     */
    public function shallStop()
    {
        if ($this->shallStop) {
            return true;
        }
        $this->waitForDatabaseConnection();
        $db = DatabaseConnection::get();
        $this->shallStop = !$db->isSwitchOn();
        return $this->shallStop;
    }

    /**
     * Waits until a DatabaseConnection could be established.
     */
    public function waitForDatabaseConnection()
    {
        $isDatabaseReachable = false;
        while (!$isDatabaseReachable) {
            $db = DatabaseConnection::get();
            if ($db->isReachable()) {
                $isDatabaseReachable = true;
            }
        }
    }

    /**
     * Initialize the servers (mark the all as free).
     */
    public function initializeServers()
    {
        $queue = $this->queue;

        $db = DatabaseConnection::get();

        /* Retrieve the list of servers. */
        $servers = $queue->availableServer();

        foreach ($servers as $server) {
            $this->nping[$server] = 0;
            $db->markServerAsFree($server);
        }
    }


    /**
     * Runs the QueueManager.
     */
    public function run()
    {
        global $imageProcessingIsOnQueueManager;

        $this->waitForDatabaseConnection();
        $this->initializeServers();

        Log::info("Huygens Remote Manager started on " . date("Y-m-d H:i:s"));

        // Fill admin user information in the database
        if (!$this->fillInSuperAdminInfoInTheDatabase()) {
            Log::error("Could not store the information for the admin user in the database! " .
            "The Queue Manager was shut down!");
            return;
        }

        if (!FileserverV2::createUpDownloadFolderIfMissing()) {
            Log::error("The upload and download folders do not exist or are not writable!" .
            "The Queue Manager was shut down!");
            return;
        }

        // Instantiate database connection
        $db = DatabaseConnection::get();

        // Query the database for processing servers
        $servers = $db->getAllServers();
        if (count($servers) == 0) {
            Log::error("There are no processing servers configured in the database! " .
            "The Queue Manager was shut down!");
            return;
        }

        // Perform some database maintenance
        $db->cleanQueueFromBrokenJobs();

        // We will use the first server for the following queries.
        // Due to historical reasons, the name field can also contain the GPU ID.
        $serverNameAndGpuID = explode(" ", $servers[0]['name']);
        $server = $serverNameAndGpuID[0];

        $hucorePath = $servers[0]['huscript_path'];

        if (!$this->askHuCoreVersionAndStoreIntoDB($server, $hucorePath)) {
            Log::error("An error occurred while reading HuCore version. " .
            "The Queue Manager was shut down!");
            return;
        } else {
            Log::info("Successfully stored HuCore version in the database.");
        }

        if (!$this->storeHuCoreLicenseDetailsIntoDB($server, $hucorePath)) {
            Log::error("An error occurred while saving HuCore license details" .
            "The Queue Manager was shut down!");
            return;
        } else {
            Log::info("Successfully updated license details in the database.");
        }

        if (!$this->storeConfidenceLevelsIntoDB($server, $hucorePath)) {
            Log::error("An error occurred while storing the confidence levels in the database. " .
            "The Queue Manager was shut down!");
            return;
        } else {
            Log::info("Successfully updated confidence levels in the database.");
        }

        $queue = $this->queue;
        while (!$this->shallStop()) {
            set_time_limit(0);

            // Reduce the used cycles by going to sleep for one second
            if ($imageProcessingIsOnQueueManager) {
                sleep(1);
            }

            // Check if jobs finished and update the database. Inform the
            // user via email.
            $this->updateJobAndServerStatus();

            // Log useful stuff to spot problematic situations with the queue.
            $this->onceAnHourLog();

            // Read in a free huygens server
            while (!($queue->isLocked()) && $this->getFreeServer()) {
                // Get next Job from the queue
                $job = $this->nextJobFromQueue();

                // Exit the loop if no job is queued.
                if ($job == null) {
                    break;
                }

                Log::info("Using Huygens server: " . $this->freeServer);

                // Read in a queued job
                $desc = $job->description();
                $id = $desc->id();
                Log::info("Processing job " . $id . " on " . $job->server());

                // Create Huygens template
                if (! $job->buildAndWriteHuygensTemplate()) {
                    // Error has been logged already. Skipping.
                    continue;
                }

                // Execute the template on the Huygens server and
                // update the database state
                if (! $this->executeTemplate($job)) {
                    Log::error("An error occurred while executing HuCore template " .
                    "for job with id $id. Skipping to next job in queue.");
                    continue;
                }

                Log::info("Template has been executed successfully.");

                // Now run the actual job
                if (! $queue->startJob($job)) {
                    Log::error("An error occurred while running job with id $id." .
                        "Skipping to next job in queue.");
                    continue;
                }

                // Inform that the job has started
                $startDate = date("Y-m-d H:i:s");
                Log::info("Job with id $id has been started on $startDate.");
            }
        }

        // Inform
        Log::warning("Huygens Remote Manager stopped via database switch on " . date("Y-m-d H:i:s"));
    }

    /**
     * Prepares the text with the summary of the Job parameters to be sent
     * to the user.
     * @param Job $job A Job object.
     * @return string The text to be later sent by email.
     */
    private function parameterText(Job $job)
    {
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

    /**
     * Increment the number of attempts of pining a server with given name
     * @param string $name Name of the server.
     */
    private function incNPing($name)
    {
        $this->nping[$name]++;
    }

    /**
     * Asks HuCore to provide its version number and store it in the DB
     * @param string server Server on which hucore is running. Omit for localhost.
     * @param string server Full path to hucore on the specified server.
     * @return bool True if asking the version and storing it in the database was
     * successful, false otherwise.
     */
    private function askHuCoreVersionAndStoreIntoDB($server, $hucorePath)
    {
        $huversion = HuygensTools::askHuCore("reportVersionNumberAsInteger", "", $server, $hucorePath);
        if ($huversion == null) {
            Log::error("Could not retrieve HuCore version!");
            return false;
        }
        $huversion = $huversion["version"];
        Log::info("HuCore version = " . $huversion);
        if (!System::setHuCoreVersion($huversion)) {
            return false;
        }
        return true;
    }

    /**
     * Gets license details from HuCore and saves them into the db.
     * @param string server Server on which hucore is running. Omit for localhost.
     * @param string server Full path to hucore on the specified server.
     * @return bool True if everything went OK, false otherwise.
     */
    private function storeHuCoreLicenseDetailsIntoDB($server, $hucorePath)
    {
        $licDetails = HuygensTools::askHuCore("reportHuCoreLicense", "", $server, $hucorePath);
        if ($licDetails == null) {
            Log::error("Could not retrieve license details!");
            return false;
        }


        Log::info($licDetails);

        // Store the license details in the database.
        $db = DatabaseConnection::get();
        if (!$db->storeLicenseDetails($licDetails['license'])) {
            Log::error("Could not store license details in the database!");
            return false;
        }

        return true;
    }


    /**
     * Store the confidence levels returned by huCore into the database
     * for faster retrieval.
     * @param string server Server on which hucore is running. Omit for localhost.
     * @param string server Full path to hucore on the specified server.
     * @return bool True if asking the version and storing it in the database was
     * successful, false otherwise.
     */
    private function storeConfidenceLevelsIntoDB($server, $hucorePath)
    {

        // Get the confidence levels string from HuCore
        $result = HuygensTools::askHuCore("reportFormatInfo", "", $server, $hucorePath);
        if ($result == null) {
            Log::error("Could not retrieve confidence levels!");
            return false;
        }

        $confidenceLevelString = $result["formatInfo"];

        // Parse the confidence levels string
        $confidenceLevels =
            $this->parseConfidenceLevelString($confidenceLevelString);

        // Store the confidence levels in the database
        $db = DatabaseConnection::get();
        if (!$db->storeConfidenceLevels($confidenceLevels)) {
            Log::error("Could not store confidence levels to the database!");
            return false;
        }
        return true;
    }

    /**
     * Parse the string returned by HuCore containing the confidence
     * levels for parameters and return them in an array.
     * @param string $confidenceLevelString Confidence level returned by hucore.
     * @return array An array containing the parameter confidence levels.
     */
    private function parseConfidenceLevelString($confidenceLevelString)
    {

        // Break down the string into per-file substrings
        $confidenceLevelString = str_replace(
            '}}',
            '}}<CUT_HERE>',
            $confidenceLevelString
        );

        $groups = explode('<CUT_HERE> ', $confidenceLevelString);

        // Prepare the output array
        $confidenceLevels = array();

        // Confidence level regexp
        $levelRegExp = '(asIs|reported|estimated|default|verified)';

        // Process the substrings
        foreach ($groups as $group) {
            $match = array();
            preg_match(
                "/(\A\w{2,16})(\s{1,2})(\{sampleSizes\s\{.+)/",
                $group,
                $match
            );

            // Get the parts
            if ((!isset($match[1])) || (!isset($match[3]))) {
                $msg = "Could not parse confidence levels!";
                Log::error($msg);
                exit($msg);
            }
            $fileFormat = $match[1];
            $parameters = $match[3];

            // Prepare the parameter array
            $params = array(
                "fileFormat" => "none",
                "sampleSizesX" => "default",
                "sampleSizesY" => "default",
                "sampleSizesZ" => "default",
                "sampleSizesT" => "default",
                "iFacePrim" => "default",
                "iFaceScnd" => "default",
                "pinhole" => "default",
                "chanCnt" => "default",
                "imagingDir" => "default",
                "pinholeSpacing" => "default",
                "objQuality" => "default",
                "lambdaEx" => "default",
                "lambdaEm" => "default",
                "mType" => "default",
                "NA" => "default",
                "RIMedia" => "default",
                "RILens" => "default",
                "photonCnt" => "default",
                "exBeamFill" => "default",
                "stedMode" => "default",
                "stedLambda" => "default",
                "stedSatFact" => "default",
                "stedImmunity" => "default",
                "sted3D" => "default");

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
                Log::error($msg);
                exit($msg);
            }
            if (isset($match[2])) {
                $params['sampleSizesY'] = $match[2];
            } else {
                $msg = "Could not find confidence level for file format " .
                    $fileFormat . " and parameter sampleSizesY!";
                Log::error($msg);
                exit($msg);
            }
            if (isset($match[3])) {
                $params['sampleSizesZ'] = $match[3];
            } else {
                $msg = "Could not find confidence level for file format " .
                    $fileFormat . " and parameter sampleSizesZ!";
                Log::error($msg);
                exit($msg);
            }
            if (isset($match[4])) {
                $params['sampleSizesT'] = $match[4];
            } else {
                $msg = "Could not find confidence level for file format " .
                    $fileFormat . " and parameter sampleSizesT!";
                Log::error($msg);
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
                Log::error($msg);
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
                Log::error($msg);
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
                Log::error($msg);
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
                Log::error($msg);
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
                Log::error($msg);
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
                Log::error($msg);
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
                Log::error($msg);
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
                    $fileFormat . " and parameter lambdaEx!";
                Log::error($msg);
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
                    $fileFormat . " and parameter lambdaEm!";
                Log::error($msg);
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
                    $fileFormat . " and parameter mType!";
                Log::error($msg);
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
                    $fileFormat . " and parameter NA!";
                Log::error($msg);
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
                    $fileFormat . " and parameter RIMedia!";
                Log::error($msg);
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
                    $fileFormat . " and parameter RILens!";
                Log::error($msg);
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
                    $fileFormat . " and parameter photonCnt!";
                Log::error($msg);
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
                    $fileFormat . " and parameter exBeamFill!";
                Log::error($msg);
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
                        $fileFormat . " and parameter $stedParam!";
                    Log::error($msg);
                    exit($msg);
                }
            }

            // Store the parameters for current file format
            $confidenceLevels[$fileFormat] = $params;
        }

        return $confidenceLevels;
    }

    /**
     * Transfers info about the admin user from the configuration
     * files to the database.
     */
    private function fillInSuperAdminInfoInTheDatabase()
    {
        global $email_admin;

        $success = true;

        $db = DatabaseConnection::get();

        // This is a corner case: if the QM is started before the
        // database update to version 15, the sql query that relies on the
        // role to be set will fail and the QM will fail.
        // @TODO Remove this at next database revision 16.
        if (System::getDBCurrentRevision() < 15) {
            $sql = "SELECT email FROM username WHERE name='admin';";
            $email = $db->queryLastValue($sql);
            // If the e-mail is not in the database yet, we store it.
            if ($email == "") {
                $sqlUp = "UPDATE username SET email='$email_admin' WHERE name='admin';";
                $success &= $db->execute($sqlUp);
            }
        } else {
            $role = UserConstants::ROLE_SUPERADMIN;
            $sql = "SELECT email FROM username WHERE name='admin' AND role='$role';";
            $email = $db->queryLastValue($sql);
            // If the e-mail is not in the database yet, we store it.
            if ($email == "") {
                $sqlUp = "UPDATE username SET email='$email_admin' WHERE name='admin' AND role='$role';";
                $success &= $db->execute($sqlUp);
            }
        }
        return $success;
    }
}
