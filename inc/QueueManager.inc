<?php

// Module QueueManager.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.

require_once ("Setting.inc");
require_once ("Database.inc");
require_once ("JobDescription.inc");
require_once ("hrm_config.inc");
require_once ("Fileserver.inc");
require_once ("Shell.inc");
require_once ("Mail.inc");
require_once ("Job.inc");
require_once ("JobQueue.inc");

//!---------------------------------------------------------
// @class    QueueManager
// @desc     Translate job description to huygens script
//           and run it on a free server
//!---------------------------------------------------------

Class QueueManager {
	var $queue;
	var $freeServer;
	var $job;
	var $shallStop;
	// TODO make it a local variable
	var $stopTime;
	var $nping;

	function QueueManager() {
		$this->runningJobs = array ();
		$this->queue = new JobQueue();
		$this->shallStop = False;
		$this->nping = array();
	}

	function setJob($job) {
		$this->job = $job;
	}

	function job() {
		return $this->job();
	}

	function executeScript($job) {
		global $imageProcessingIsOnQueueManager;
		global $copy_images_to_huygens_server;
		global $logdir;
		
		$server = $this->freeServer;
		// server name without proc number
		$s = split(" ", $server);
		$server_hostname = $s[0];
		$desc = $job->description();
		$clientScriptPath = $desc->sourceFolder();
		$scriptName = $job->scriptName();
		if (!$imageProcessingIsOnQueueManager && $copy_images_to_huygens_server) {
			$clientScriptPath = $this->copyImagesToServer($server_hostname);
			report("images copied to IP server", 1);
		}
		$proc = newExternalProcessFor($server, $server . "_" .$job->id() . "_out.txt", $server . "_" .$job->id(). "_error.txt");
		report("shell process created", 1);
		$ssh = $proc->runShell();
		report("running shell: $clientScriptPath$scriptName", 1);
		$pid = $proc->runHuygensScript($clientScriptPath . $scriptName);
		// TODO refactor this <<
		// check if Huygens outputs any interactive dialog
		# sleep(5);

                // This keeps the queue manager blocked until the job is
                // completed. I guess that's not the intention! I'll deactivate
                // it by now.
                /*
                while (True) {
			if (feof($proc->out_file)) {
                            report ("EOF ".$proc->out_file.": ", 1);
				break;
                        }
			$input = fgets($proc->out_file);

                        // Why is this error processing necessary here?
			if (strstr($input, "Microscope types of original and PSF differ:")) {
				$warning_file = fopen($logdir . "/" . $server . "_warning.txt", "a");
				fwrite($warning_file, "[" . $desc->id() . "] Microscope types of original and PSF differ\n");
				fclose($warning_file);
				break;
			}
			else if (strstr($input, "After necessary resampling the PSF is too small.")) {
				$warning_file = fopen($logdir . "/" . $server . "_warning.txt", "a");
				fwrite($warning_file, "[" . $desc->id() . "] After necessary resampling the PSF is too small\n");
				fclose($warning_file);
				break;
			}
		}
                */
		// >>
		report("running script (pid $pid)", 1);

                // Release the shell, the script in the background will keep
                // running.
                $proc->release();

                # $this->activeProcs[$job->id()] = $proc;
		$job->setPid($pid);
		$job->setServer($server);
		return ($pid > 0);
	}
	
 	function copyImagesToServer($server_hostname) {
		global $huygens_user;
		global $image_folder;
		global $image_source;
		global $image_destination;
		global $huygens_server_image_folder;
		
		$job = $this->job;
		$desc = $job->description();
		$user = $desc->owner();
		
		// TODO substitute spaces by underscores in image name to avoid processing problems with Huygens
		
		$batch = "cd \"" . $huygens_server_image_folder . "\"\n";
		$batch .= "-mkdir \"" . $user->name() . "\"\n";
		$batch .= "cd \"" . $user->name() . "\"\n";
		$batch .= "-mkdir \"" . $image_source . "\"\n";
		$batch .= "cd \"" . $image_source . "\"\n";
		$batch .= "put \"" . $image_folder . "/" . $user->name() . "/" . $image_source . "/" . $job->scriptName() . "\"\n";
		
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
				$filename = $image_folder . "/" . $user->name() . "/" . $image_source . "/" . $value;
				if (stristr($filename, ".ics"))  {
					$batch .= "put \"" . $filename . "\"\n";
					$filename = eregi_replace(".ics", ".ids", $filename);
					$batch .= "put \"" . $filename . "\"\n";
				}
				else {
					$batch .= "put \"" . $filename . "\"\n";
				}
				if (sizeof($path) > 0) {
					for ($i = 0; $i < sizeof($path) - 1; $i++) {
						$batch .= "cd ..\n";
					}
				}
			}
			$batch .= "cd \"" . $huygens_server_image_folder . "/" . $user->name() . "/" . $image_source . "\"\n";
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
			$filename = $image_folder . "/" . $user->name() . "/" . $image_source . "/" . $file;
			if (stristr($filename, ".ics"))  {
				$batch .= "put \"" . $filename . "\"\n";
				$filename = substr($filename, 0, strrpos($filename, '.ics')) . ".ids";
				$batch .= "put \"" . $filename . "\"\n";
			}
			else if (stristr($filename, ".tif") || stristr($filename, ".tiff")) {
				// TODO: if ImageFileFormat = single TIFF file, do not send corresponding series
				$basename = preg_replace("/([^_]+|\/)(_)(T|t|Z|z|CH|ch)([0-9]+)(\w+)(\.)(\w+)/", "$1$6$7", $filename);
				$name = preg_replace("/(.*)\.tiff?$/", "$1", $basename);
				$batch .= "put \"" . $name . "*\"\n";
			}
			else if (stristr($filename, ".stk")) {
				// if ImageFileFormat = STK time series, send all timepoints
				if (stripos($filename, "_t")) {
					$basename = preg_replace("/([^_]+|\/)(_)(T|t)([0-9]+)(\.)(\w+)/", "$1", $filename);
					//$name = preg_replace("/(.*)\.[STK|stk]$/", "$1", $basename);
					$batch .= "put \"" . $basename . "*\"\n";
				}
				else {
					$batch .= "put \"" . $filename . "\"\n";
				}
			}
			else {
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
				
		//report("\nBATCH \n$batch", 1);
		
		$batch_filename = $image_folder . "/" . $user->name() . "/" . "batchfile_" . $desc->id();
		
		$batchfile = fopen($batch_filename, 'w');
		fwrite($batchfile, $batch);
		fclose($batchfile);
		
		// TODO refactor this << move to Shell
		exec("sftp -b " . $batch_filename . " " . $huygens_user . "@" . $server_hostname);
                exec("rm -f " . $batch_filename);
		// >>
		
		return $huygens_server_image_folder . $user->name() . "/" . $image_source . "/";
	}
	
	function nextJobFromQueue() {
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
		if ($pausedJobs)
			$queue->restartPausedJobs();
		if ($jobDescription == NULL)
			return NULL;
		$job = new Job($jobDescription);
		$job->setServer($this->freeServer);
		return $job;
	}


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
		// clean up server
		//$scriptfilename = $path . '/' . $job->scriptName();
		//if (file_exists($scriptfilename)) unlink($scriptfilename);
		//report("removing script", 1);
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
	
	// TODO refactor this
	/*function retrieveImagesFromServer($server_hostname, $username) {
		global $imageProcessingIsOnQueueManager;
		global $image_folder;
		global $image_source;
		global $image_destination;
		global $huygens_server_image_folder;
		global $huygens_user;
		global $huygens_group;
		global $copy_images_to_huygens_server;
		if (!$imageProcessingIsOnQueueManager && $copy_images_to_huygens_server) {
			$lpath = $image_folder . "/" . $username;
			$rpath = $huygens_server_image_folder . $username;
			error_log("retrieving files .................");
			//error_log("("."scp -r ".$huygens_user."@".$server_hostname.":".$rpath."/".$image_destination." ".$lpath.")");
			$output = exec("scp -r " . $huygens_user . "@" . $server_hostname . ":" . $rpath . "/" . $image_destination . " " . $lpath);
			error_log("> " . $output);
			error_log("deleting files ...................\n");
			exec("ssh " . $huygens_user . "@" . $server_hostname . " rm -f " . $rpath . "/" . $image_source . "/" . $job->scriptName());
			//error_log("("."ssh ".$huygens_user."@".$server_hostname." rm -f ".$rpath."/".$image_source."/".$job->scriptName().")");
			exec("ssh " . $huygens_user . "@" . $server_hostname . " rm -rf " . $rpath . "/" . $image_destination . "/*");
			exec("ssh " . $huygens_user . "@" . $server_hostname . " rm -rf " . $rpath . "/" . $image_source . "/*");
			//error_log("("."ssh ".$huygens_user."@".$server_hostname." rm -rf ".$rpath."/".$image_destination."/*".")");
			error_log("> " . $output);
			exec("chown -R " . $huygens_user . ":" . $huygens_group . " " . $lpath);
		}
	}*/
        
        // TODO refactor semantics of this method
        function restoreOwnership($username) {
                global $resultImagesOwnedByUser;
                global $image_user;
                global $image_group;
                global $image_folder;
                
                //if ($resultImagesOwnedByUser)
                //      ;
                //else
                        //$result = exec("chown -R " . $huygens_user . ":" . $huygens_group . " " . $image_folder . "/" . $username);
                        //error_log($result);
                        
                $result = exec("sudo chown -R " . $image_user . ":" . $image_group . " " . $image_folder . "/" . $username);
                error_log("Restoring ownership... " . $result);
        }
	
	// TODO refactor this
	function updateJobAndServerStatus() {
		global $imageProcessingIsOnQueueManager;
		global $use_accounting_system;
		global $accounting_client_name;
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
            report(count($runningJobs) . " job" . (count($runningJobs) == 1 ? " is" : "s are") . " running", 2);
            // Because something is running, we are not in a hurry to continue.
            // Delay execution.
            sleep(5);
        }
		foreach ($runningJobs as $job) {
			$desc = $job->description();
			$user = $desc->owner();
			$id = $job->id();
			
			// Check if fileserver is reachable
			$fileserver = new Fileserver($user->name());
			if (!$fileserver->isReachable())
				continue;
			
			// Check if Huygens host is reachable
			$proc = newExternalProcessFor($job->server(),
                                $job->server() . "_" .$job->id() . "_out.txt",
                                $job->server() . "_" .$job->id(). "_error.txt");
			if (!$proc->ping())
				continue;
			
			// Check finished marker
			$finished = $job->checkProcessFinished();
			if (!$finished) {
                            continue;
                        }
			report("checked finished process", 2);
			
			// Check result image
			$resultSaved = $job->checkResultImage();
			report("checked result image", 2);
			
			// If fileshare is not on the same host as Huygens
			if (!$imageProcessingIsOnQueueManager)
				$this->restoreOwnership($user->name());
			
			// Check if error occured
			$errorOccured = $this->errorOccured($job);
			
			// Notify user
			$startTime = $queue->startTime($job);
                        $errorFile = $logdir . "/" . $job->server() .
                            "_" .$job->id(). "_error.txt";
                        $logFile = $logdir . "/" . $job->server() .
                            "_" .$job->id(). "_out.txt";
			// TODO add warning messages notification
			if (!$resultSaved || $errorOccured) {
				report("finishing job " . $desc->id() . " with error on " . $job->server(), 1);
				// Clean up server
				$this->cleanUpFileServer($job);
				// Reset server and remove job from the job queue (update database) 
				$this->stopTime = $queue->stopJob($job);
				// Write email
				$message = "";
                                if (!$resultSaved) {
                                    $message .= "\nNo result file stored in the destination directory.\n";
                                }
				if (file_exists($errorFile))
                                    $message .= "\n\n- HUYGENS ERROR REPORT (stderr) --------------\n\n". file_get_contents($errorFile);
                                if (file_exists($logFile))
                                    $message .= "\n\n- HUYGENS REPORT (stdout) --------------------\n\n". file_get_contents($logFile);
				if ($send_mail) 
                                    $this->notifyError($job, $message, $startTime);

                                // TODO: copy log files to fileserver using the
                                // same name as the output result.
                                if (file_exists($errorFile)) {
                                    $gFile = $logdir . "/" . $job->server() .
                                    "_error.txt";
                                    # Accumulate error logs
                                    exec ("cat \"$errorFile\" >> \"$gFile\"");
                                    unlink($errorFile);	
                                }

				if (file_exists($logFile))
					unlink($logFile);	
			}
			else {
				report("job " . $desc->id() . 
                                        " completed on " . $job->server(), 1);
				// Report information to statistics table
				$db = new DatabaseConnection();
				$db->updateStatistics($job, $startTime);
				// Clean up server
				$this->cleanUpFileServer($job);
				// Reset server and remove job from the job queue (update database) 
				$this->stopTime = $queue->stopJob($job);
				$this->writeParameterFile($job, $startTime,
                                        $logFile, $errorFile);
				// Write email
				if ($send_mail) $this->notifySuccess($job, $startTime);
				if (file_exists($errorFile)) { 
				    unlink($errorFile);
				} 
				if (file_exists($logFile))
					unlink($logFile);	
				// make withdrawal of hours
				if ($use_accounting_system) $this->makeWithdrawal($desc->owner(), $accounting_client_name, $startTime, $this->stopTime, $desc->credit(), $desc->group);
                                # $proc = $this->activeProcs[$id];
                                # $debug = var_export($this->activeProcs, true);
                                # report ("Releasing shell, ".
                                 #       "unsetting activeProc $id", 2);
                                # $proc->release();
                                # unset($this->activeProcs[$id]);
			}
			
			/*$result = False;
			if ($imageProcessingIsOnQueueManager || (!$imageProcessingIsOnQueueManager && $finished)) {
				$result = $job->checkResultImage();
				report("checked result image", 2);
			}*/
			
			// TODO check for redundant file retrieval
			/*if (!$imageProcessingIsOnQueueManager && $result) {
				$this->retrieveImagesFromServer($server_hostname, $username);
			}*/
			
			// Notify user
			/*$startTime = $queue->startTime($job);
			$errorOccured = $this->errorOccured($job);
			// TODO
			$warningOccured = file_exists($job->server() . '_warning.txt');
			// manage warning messages when processing a job
			// TODO refactor
			if ($finished && $warningOccured) {
				$warnings = file_get_contents($job->server() . '_warning.txt');
				$input = $warnings;
				if ($warnings)
					$input = ereg_replace("^.*\[$id] ([a-zA-Z ]*)\n.*$", "\\1", $warnings);
				if ($input != $warnings) {
					report("finishing job $id with warning on " . $job->server(), 1);
					// warning
					$this->cleanUpFileServer($job);
					// remove job
					$this->stopTime = $queue->stopJob($job);
					// write email
					$message = $input;
					if ($send_mail) $this->notifyWarning($job, $message, $startTime);
					//if (file_exists($errorFile)) { 
					//    unlink($errorFile);
					//}
				}
			} else
				if (($finished && !$result) || $errorOccured) {
					report("finishing job $id with error on " . $job->server(), 1);
					// error
					$this->cleanUpFileServer($job);
					// remove job
					$this->stopTime = $queue->stopJob($job);
					// write email
					$message = '';
					if (file_exists($errorFile)) {
						$message = file_get_contents($errorFile);
					}
					if ($send_mail) $this->notifyError($job, $message, $startTime);
					//if (file_exists($errorFile)) { 
					//    unlink($errorFile);
					//} 
				}
				else if ($finished && $result) {
					report("job $id completed on " . $job->server(), 1);
					// finsihed. remove job, write email, clean up huygens server
					// clean up server
					$this->cleanUpFileServer($job);
					// remove job
					$this->stopTime = $queue->stopJob($job);
					// write email
					$this->writeParameterFile($job, $startTime, $logFile, $errorFile);
					if ($send_mail) $this->notifySuccess($job, $startTime);
					//if (file_exists($errorFile)) { 
					//    unlink($errorFile);
					//} 
					// make withdrawal of hours
					if ($use_accounting_system) {
						$this->makeWithdrawal($desc->owner(), $accounting_client_name, $startTime, $this->stopTime, $desc->credit(), $desc->group);
					}
				}*/
		}
	}

	function makeWithdrawal($user, $client, $startTime, $stopTime, $credit, $group) {
		global $accounting_transaction_path;
		$stopTimeForFilename = str_replace(" ", "-", $stopTime);
		$filename = $accounting_transaction_path . str_replace(":", "-", $stopTimeForFilename);
		$startTimeForMessage = str_replace(":", "/", $startTime) . ".0";
		$endTimeForMessage = explode(".", $stopTime);
		$endTimeForMessage = str_replace(":", "/", $endTimeForMessage[0]) . ".0";
		$message = 'MessageRequestExecuteWithdrawal:' . $user->name() . ':' . $client . ':' . $startTimeForMessage . ':' . $endTimeForMessage . ':' . $credit . ":" . $group;
		$file = fopen($filename, 'w');
		fwrite($file, $message);
		fclose($file);
		exec("hrmAccountingRelay");
		report("created transaction: $message", 1);
	}

	function writeParameterFile($job, $startTime, $logFile, $errorFile) {
		global $resultImagesOwnedByUser;
                global $imageProcessingIsOnQueueManager;
		$result = False;
		$desc = $job->description();
		$sourceFileName = $desc->sourceImageNameWithoutPath();
		$destFileName = $desc->destinationImageNameWithoutPath();
		$text = '';
		$text = $text . "Your job started at $startTime and finished".
                    " at " . date("Y-m-d H:i:s") . ".\n\n";
		$text = $text . "The image $sourceFileName has been ".
                    "successfully treated by Huygens.\n\n";
                $text = $text . "You will find the resulting image ".
                    "($destFileName) in your destination folder.\n";
		$text = $text . $this->parameterText($job);
		$imageName = $desc->destinationImageName();
		$user = $desc->owner();
		$username = $user->name();
		$fileserver = new Fileserver($username);
		$path = $fileserver->destinationFolderFor($desc);

                if (file_exists($errorFile))
                    $text .= "\n\n- HUYGENS ERROR REPORT (stderr) --------------\n\n". file_get_contents($errorFile);
                if (file_exists($logFile))
                    $text .= "\n\n- HUYGENS REPORT (stdout) --------------------\n\n". file_get_contents($logFile);

                $parameterFileName = $path . $imageName . '.log.txt';

                // Why (over)writing this file only if it already exists? Make
                // it true by now to always write it:
		if (True || file_exists($parameterFileName)) {
			//$parameterFileName = $path . '/' . $imageName . '.txt';
			$file = fopen($parameterFileName, "w");
			$result = !$result && (fwrite($file, $text) > 0);
			fclose($file);
			if ($resultImagesOwnedByUser) {
				chown($parameterFileName, $username);
			}
			// TODO refactor this
			else if (!$imageProcessingIsOnQueueManager) {
				$this->restoreOwnership($user->name());
			}
		}
		return $result;
	}
	
	// TODO check what happens if a job fails and the next one succeeds
	// TODO refactor this!
	function errorOccured($job) {
		global $imageProcessingIsOnQueueManager;
		//if ($imageProcessingIsOnQueueManager) {
			return False;
		//}
		// The following is never run, but for completeness we add the logdir
		global $logdir;
		$errorFile = $logdir . "/" . $job->server() .  "_" .$job->id(). '_error.txt';
		$message = '';
		if (file_exists($errorFile)) {
			$message = file_get_contents($errorFile);
		}
		if (strstr($message, 'SignalError')) {
			return True;
		}
		if (strstr($message, 'failed to allocate')) {
			return True;
		}
		if (strstr($message, 'Error encountered')) {
			return True;
		}
		return False;
	}

	function notifySuccess($job, $startTime) {
		global $email_sender;
                global $hrm_url;
                global $useThumbnails;
		$desc = $job->description();
		$user = $desc->owner();
                $fileserver = new Fileserver($user->name());
		$emailAddress = $user->emailAddress();
		$sourceFileName = $desc->sourceImageNameWithoutPath();
		//$destFileName = $desc->destinationImageName();
		$destFileName = $desc->destinationImageNameWithoutPath();
		$text = "This is a mail generated automatically by the Huygens Remote Manager.\n";
		$text = $text . "Your job started at $startTime and finished at " . date("Y-m-d H:i:s") . ".\n";
		$text = $text . "The image $sourceFileName was successfully processed by Huygens.\n";
		$text = $text . "You will find the resulting image ($destFileName) in your destination folder.\n";
                $imageName = $desc->destinationImageNameAndExtension();
                if ( $useThumbnails ) {
                    $link = $hrm_url.
                           "/file_management.php?compareResult=".
                           urlencode($imageName);
                    $text .= "\nYou can find a preview of the result at ".$link.
                             " (login required).\n\n";
                }
                $remarksFile =
                    $fileserver->destinationFolder(). "/".
                    $imageName.".remarks.txt";
		if (file_exists($remarksFile)) {
                    $text = $text . file_get_contents($remarksFile)."\n\n";
                }
		$text = $text . "Best regards,\n";
		$text = $text . "Huygens Remote Manager\n";
		$text = $text . $this->parameterText($job);
		$mail = new Mail($email_sender);
		$mail->setReceiver($emailAddress);
		$mail->setSubject('Your huygens job finished successfully');
		$mail->setMessage($text);
		$mail->send();
	}

	function notifyError($job, $message, $startTime) {
		global $email_sender;
		$job->createScript();
		$desc = $job->description();
		$user = $desc->owner();
		$emailAddress = $user->emailAddress();
		$sourceFileName = $desc->sourceImageNameWithoutPath();
		//$destFileName = $desc->destinationImageName();
		$text = "\nThis is a mail generated automatically by the Huygens Remote Manager.\n\n";
		$text = $text . "Sorry, the processing of the image \n$sourceFileName\nhas been terminated with an error.\n\n";
		$text = $text . "Your job started at $startTime and failed at " . date("Y-m-d H:i:s") . ".";
		$text = $text . "\n\n";
		$text = $text . "Best regards,\n";
		$text = $text . "Huygens Remote Manager\n";
		$text = $text . "-------------------------------------";
		$text = $text . "\n\n";
		$text = $text . $message;
		$text = $text . "\n\n- SUMMARY ---------------------------\n\n";
		$text = $text . $this->parameterText($job);

		$text = $text . "\n\n- SCRIPT ----------------------------\n\n";
		$text = $text . "What follows is the Huygens Core script executed when the error occured:\n\n";
		$text = $text . $job->script();
		$text = $text . "\n\n-------------------------------------\n\n";
		$mail = new Mail($email_sender);
		$mail->setReceiver($emailAddress);
		$mail->setSubject('Your huygens job finished with an error');
		$mail->setMessage($text);
		$mail->send();
		// also notify error to admin
		$db = new DatabaseConnection();
		$mail->setReceiver($db->emailAddress('admin'));
		$mail->send();
	}

	// notify the user of interactive dialogs silently managed by Huygens
	function notifyWarning($job, $message, $startTime) {
		global $email_sender;
		$job->createScript();
		$desc = $job->description();
		$user = $desc->owner();
		$emailAddress = $user->emailAddress();
		$sourceFileName = $desc->sourceImageNameWithoutPath();
		//$destFileName = $desc->destinationImageName();
		$text = "This is a mail generated automatically by the Huygens Remote Manager.\n";
		$text = $text . "The processing of the image \n$sourceFileName\n was completed with a warning: ";
		$text = $text . $message . "\n";
		$text = $text . "Huygens used a theoretical PSF instead of the measured PSF you provided.\n";
		$text = $text . "Your job started at $startTime and finished at " . date("Y-m-d H:i:s") . ".";
		$text = $text . "\n";
		$text = $text . "Best regards,\n";
		$text = $text . "Huygens Remote Manager\n";
		$text = $text . $this->parameterText($job);
		$mail = new Mail($email_sender);
		$mail->setReceiver($emailAddress);
		$mail->setSubject('Your huygens job finished with a warning');
		$mail->setMessage($text);
		$mail->send();
		// also notify error to admin
		/*$db = new DatabaseConnection();
		$mail->setReceiver($db->emailAddress('admin'));
		$mail->send();*/
	}
	
	function notifyPingError($name) {
                report("A ping error notification was sent on " . date("r", time()), 2);
                global $email_sender;
                global $email_admin;
                $text = "Huygens Remote Manager warning:\n"
                        . $name . " could not be pinged on " . date("r", time());
                $mail = new Mail($email_sender);
		$mail->setReceiver($email_admin);
		$mail->setSubject('Huygens Remote Manager - ping error');
		$mail->setMessage($text);
		$mail->send(); 
        }

	function parameterText($job) {
		$desc = $job->description();
		$id = $desc->id();
		$pid = $job->pid();
		$server = $job->server();
		$result = '';
		$result = $result . "\nJob id: $id (pid $pid on $server)\n";
		$result = $result . "\nImage parameters:\n\n";
		$parameterSetting = $desc->parameterSetting();
		$parameterSettingString = $parameterSetting->displayString();
		$result = $result . $parameterSettingString;
		$result = $result . "\nRestoration parameters:\n\n";
		$taskSetting = $desc->taskSetting();
		$taskSettingString = $taskSetting->displayString();
		$result = $result . $taskSettingString;
		return $result;
	}

	function getFreeServer() {
		$db = new DatabaseConnection();
		$serverNames = $db->availableServer();
		foreach ($serverNames as $name) {
			$status = $db->statusOfServer($name);
			if ($status == 'free') {
				$proc = newExternalProcessFor($name, $name . "_out.txt", $name .  "_error.txt");
				//report("found free server", 2);
				if ($proc->ping()) {
					//report("ping succeeded for $name", 2);
                                        $this->nping[$name] = 0;
					$this->freeServer = $name;
					return True;
				}
                                else {
                                        $this->incNPing($name);
                                        if ($this->nping[$name]  ==  40) {
                                                $this->notifyPingError($name);
                                        }
                                }
			}
		}
		$this->freeServer = False;
		return $this->freeServer;
	}
	
	function incNPing($name) {
		$this->nping[$name] ++;
	}

	function stop() {
		$this->shallStop = True;
	}

	function shallStop() {
		if ($this->shallStop)
			return True;
		$this->waitForDatabaseConnection();
		$db = new DatabaseConnection();
		$this->shallStop = !$db->isSwitchOn();
		return $this->shallStop;
	}

	function waitForDatabaseConnection() {
		$isDatabaseReachable = False;
		while (!$isDatabaseReachable) {
			$db = new DatabaseConnection();
			if ($db->isReachable()) {
				$isDatabaseReachable = True;
			}
		}
	}

        function run() {
            global $imageProcessingIsOnQueueManager;
            global $logdir;

            $queue = $this->queue;

            $this->waitForDatabaseConnection();
            $server = $queue->availableServer();
            // TODO refactor this in order to manage error logs per job
            foreach ($server as $name) {
                $this->nping[$name] = 0;
            }

            report("Huygens Remote Manager started on " 
                    . date("Y-m-d H:i:s") . "\n", 1);

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
                //report("Huygens server updated status", 2);

                // Read in a free huygens server
                while (!( $queue->isLocked() ) && $this->getFreeServer() ) {

                    $job =  $this->nextJobFromQueue();
                    // Exit the loop when no job is queued.
                    if ( $job == NULL ) { break; }

                    report("using Huygens server: " . $this->freeServer, 2);

                    // Read in a queued job 
                    $desc = $job->description();
                    $id = $desc->id();
                    report("processing job " . $id ." on " . $job->server(), 1);

                    // TODO check this <<
                    // If the job is compound create sub jobs and
                    // remove job otherwise create script
                    //$result = $result && $job->createSubJobsOrScript();
                    $result = $job->createSubJobsOrScript();
                    if (!$result || $desc->isCompound()) {
                        error_log("error or compound job");
                        continue;
                    }
                    report("script has been created", 1);
                    // Execute the script on the Huygens server and
                    // update the database state
                    $result = $result && $this->executeScript($job);
                    if (!$result)
                        continue;
                    report("script has been executed", 1);
                    $result = $result && $queue->startJob($job);
                    report("job has been started (" 
                            . date("Y-m-d H:i:s") . ")", 1);
                }
            }
            report("Huygens Remote Manager stopped via database switch on " 
                    . date("Y-m-d H:i:s"), 1);
        }
}
?>
