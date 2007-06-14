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

//!---------------------------------------------------------
// @function    report
// @desc        Write text to the logfile if level is not
//              bigger than log_verbosity. If logfile becomes
//              to big it is renamed and started over.
// @param       text  String  The text to be logged
// @param       level  Int    The log level of the text
// @return      Void
//!---------------------------------------------------------
function report($text, $level) {
	global $log_verbosity;
	global $logfile;
	global $logfile_max_size;

	if ($log_verbosity == 0)
		return True;
	if (file_exists($logfile) && (filesize($logfile) > $logfile_max_size * 1000 * 1000)) {
		if (file_exists($logfile . ".old")) {
			unlink($logfile . ".old");
		}
		rename($logfile, $logfile . ".old");
	}
	$file = fopen($logfile, 'a');
	if ($log_verbosity >= $level) {
		fwrite($file, $text);
		fwrite($file, "\n");
		fflush($file);
	}
	fclose($file);
}

// TODO better manage multiple hosts
function newExternalProcessFor($host, $logfilename, $errfilename) {
	global $imageProcessingIsOnQueueManager;
	$db = new DatabaseConnection();
	$huscript_path = $db->huscriptPathOn($host);
	if ($imageProcessingIsOnQueueManager)
		$shell = new LocalExternalProcess($host, $huscript_path, $logfilename, $errfilename);
	else
		$shell = new ExternalProcess($host, $huscript_path, $logfilename, $errfilename);
	return $shell;
}

Class Job {
	var $script;
	var $jobDescription;
	var $server;
	var $pid;
	var $status;

	function Job($jobDescription) {
		$this->jobDescription = $jobDescription;
		$this->script = '';
	}
	function description() {
		return $this->jobDescription;
	}

	function setServer($server) {
		$this->server = $server;
	}

	function createSubJobsOrScript() {
		$result = True;
		$desc = $this->jobDescription;
		if ($desc->isCompound()) {
			$result = $result && $desc->createSubJobs();
			if ($result)
				error_log("created sub jobs");
			report("created sub jobs", 1);
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

	function server() {
		return $this->server;
	}

	function script() {
		return $this->script;
	}

	function pid() {
		return $this->pid;
	}

	function setPid($pid) {
		$this->pid = $pid;
	}

	function status() {
		return $this->status;
	}

	function setStatus($status) {
		$this->status = $status;
	}

	function createScript() {
		global $huscript_call;

		$desc = $this->description();
		$setting = $desc->parameterSetting();
		$this->script = "set id [pid]" . "\n" . 'puts "pid=$id\n"' . "\n";
		$this->script = $this->script . "huOpt verb -mode noQs\n";
		$this->script = $this->script . "catch {\n";
		$this->script = $this->script . "huOpt gundo off\n";
		if ($setting->isMultiChannel() && ($setting->numberOfChannels() > 1)) {
			$this->script = $this->script . $desc->putMultiChannelScriptOn('');
		} else {
			$this->script = $this->script . $desc->putScriptOn('');
		}
		$this->script = $this->script . "}\n";
		$id = $desc->id();
		$finishedMarker = $desc->sourceFolder() . '.finished_' . "$id";
		$this->script = $this->script . "exec touch " . '"' . $finishedMarker . '"' . "\n";
		$this->script = $this->script . "exit\n";
	}

	function scriptName() {
		$desc = $this->description();
		$result = ".hrm_" . $desc->id() . ".tcl";
		return $result;
	}

	function writeScript() {
		$result = True;
		$desc = $this->description();
		$scriptName = $this->scriptName();
		$user = $desc->owner();
		$username = $user->name();
		$fileserver = new Fileserver($username);
		$scriptPath = $fileserver->sourceFolder();
		$scriptFile = $scriptPath . "/" . $scriptName;
		$file = fopen($scriptFile, "w");
		$result = $result && (fwrite($file, $this->script) > 0);
		fclose($file);
		report("Wrote script $scriptFile", 1);
		return $result;
	}

	// TODO refactor
	function checkResultImage() {
		global $imageProcessingIsOnQueueManager;
		global $huygens_user;
		global $image_source;
		global $huygens_server_image_folder;
		
		clearstatcache();
		$server = $this->server;
		// server name without proc number
		$s = split(" ", $server);
		$server_hostname = $s[0];
		$queue = new JobQueue();
		$desc = $this->description();
		$user = $desc->owner();
		$username = $user->name();
		$fileserver = new Fileserver($username);
		$path = $fileserver->destinationFolderFor($desc);
		$outfilename = $desc->id();
		// if fileshare is not on the same host as Huygens
		if (!$imageProcessingIsOnQueueManager) {
			$image = $huygens_server_image_folder . $user->name() . "/" . $image_source . "/*" . $outfilename . "*";
			exec("(cd " . $path . " && scp " . $huygens_user . "@" . $server_hostname . ":" . $image . " .)");
			error_log("retrieving result image -> " . "(cd " . $path . " && scp " . $huygens_user . "@" . $server_hostname . ":" . $image . " .)");
		}
		$fileNameExists = $fileserver->folderContains($path, $outfilename);
		$newFileWritten = $fileserver->folderContainsNewerFile($path, $queue->startTime($this));
		$result = $fileNameExists || $newFileWritten;
		return $result;
	}

	function checkProcessFinished() {
		global $imageProcessingIsOnQueueManager;
		global $image_source;
		global $huygens_server_image_folder;
		global $huygens_user;
		clearstatcache();
		$server = $this->server;
		// server name without proc number
		$s = split(" ", $server);
		$server_hostname = $s[0];
		$desc = $this->description();
		$user = $desc->owner();
		$username = $user->name();
		$fileserver = new Fileserver($username);
		$path = $fileserver->sourceFolder();
		$id = $desc->id();
		$finishedMarker = '.finished_' . "$id";
		// if fileshare is not on the same host as Huygens
		if (!$imageProcessingIsOnQueueManager) {
			$marker = $huygens_server_image_folder . $user->name() . "/" . $image_source . "/" . $finishedMarker;
			$result = exec("ssh " . $huygens_user . "@" . $server_hostname . " ls " . $marker);
			if (strstr($result, "No such file or directory")) {
				exec("(cd " . $path . " && scp " . $huygens_user . "@" . $server_hostname . ":" . $marker . " .)");
				error_log("retrieving finished marker");
			}
		}
		$result = file_exists($path . '/' . $finishedMarker);
		$proc = newExternalProcessFor($this->server(), $this->server() . "_out.txt", $this->server() . "_error.txt");
		$result = !$proc->existsHuygensProcess($this->pid());
		if (!$result && $proc->isHuygensProcessSleeping($this->pid())) {
			$proc->rewakeHuygensProcess($this->pid());
		}
		return $result;
	}
}

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
	var $stopTime;

	function QueueManager() {
		$this->runningJobs = array ();
		$this->queue = new JobQueue();
		$this->shallStop = False;
	}

	function setJob($job) {
		$this->job = $job;
	}

	function job() {
		return $this->job();
	}

	function executeScript() {
		global $imageProcessingIsOnQueueManager;
		
		$job = $this->job;
		$server = $this->freeServer;
		// server name without proc number
		$s = split(" ", $server);
		$server_hostname = $s[0];
		$desc = $job->description();
		$clientScriptPath = $desc->sourceFolder();
		$scriptName = $job->scriptName();
		if (!$imageProcessingIsOnQueueManager)
			$clientScriptPath = $this->copyImagesToServer($server_hostname);
		$proc = newExternalProcessFor($server, $server . "_out.txt", $server . "_error.txt");
		report("shell process created", 1);
		$ssh = $proc->runShell();
		report("running shell", 1);
		$pid = $proc->runHuygensScript($clientScriptPath . $scriptName);
		// TODO refactor this <<
		// check if Huygens outputs any interactive dialog
		sleep(5);
		while (True) {
			if (feof($proc->out_file))
				break;
			$input = fgets($proc->out_file);
			if (strstr($input, "Microscope types of original and PSF differ:")) {
				$warning_file = fopen($server . "_warning.txt", "a");
				fwrite($warning_file, "[" . $desc->id() . "] Microscope types of original and PSF differ\n");
				fclose($warning_file);
				break;
			}
			else if (strstr($input, "After necessary resampling the PSF is too small.")) {
				$warning_file = fopen($server . "_warning.txt", "a");
				fwrite($warning_file, "[" . $desc->id() . "] After necessary resampling the PSF is too small\n");
				fclose($warning_file);
				break;
			}
		}
		// >>
		report("running script (pid $pid)", 1);
		$proc->release();
		$job->setPid($pid);
		$job->setServer($server);
		$this->job = $job;
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
		
		$files = $desc->files();
		foreach ($files as $file) {
			$path = split("/", $file);
			if (sizeof($path) > 0) {
				for ($i = 0; $i < sizeof($path) - 1; $i++) {
					$batch .= "-mkdir \"" . $path[$i] . "\"\n";
					$batch .= "cd \"" . $path[$i] . "\"\n";
				}
			}
			$filename = $image_folder . "/" . $user->name() . "/" . $image_source . "/" . $file;
			$components = split(".", strtolower($filename));
			if ($components[1] == ".ics")  {
				$batch .= "put \"" . $filename . "\"\n";
				$filename = eregi_replace(".ics", ".ids", $filename);
				$batch .= "put \"" . $filename . "\"\n";
			}
			else if (strstr($filename, ".tif") || strstr($filename, ".tiff")) {
				// TODO: if ImageFileFormat = single TIFF file, do not send corresponding series
				$basename = preg_replace("/([^_]+|\/)(_)(t|z|ch)([0-9]+)(\w+)(\.)(\w+)/", "$1$6$7", $filename);
				$name = preg_replace("/(.*)\.tiff?$/", "$1", $basename);
				$batch .= "put \"" . $name . "*\"\n";
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
				report("fileserver not reachable", 2);
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
		$this->job = $job;
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
			unlink($finishedMarker);
			report("removing finished marker", 1);
		}
		// remove job
		$this->stopTime = $queue->stopJob($job);
		report("stopped job (" . date("l d of F Y h:i:s A") . ")\n", 1);
	}
	
	// TODO refactor this
	function retrieveImagesFromServer($server_hostname, $username) {
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
	}
	
	// TODO refactor this
	function updateJobAndServerStatus() {
		global $imageProcessingIsOnQueueManager;
		global $use_accounting_system;
		global $accounting_client_name;
		
		$send_mail = True;
		
		$queue = $this->queue;
		$queue->removeMarkedJobs();
		$runningJobs = $queue->runningJobs();
		report(count($runningJobs) . " jobs are running", 2);
		foreach ($runningJobs as $job) {
			$desc = $job->description();
			$id = $desc->id();
			$errorFile = $job->server() . '_error.txt';
			$user = $desc->owner();
			$username = $user->name();
			// check if fileserver is reachable
			$fileserver = new Fileserver($username);
			if (!$fileserver->isReachable())
				continue;
			// check result image
			/*$result = $job->checkResultImage();
			report("checked result image", 2);*/
			// check if Huygens host is reachable
			$proc = newExternalProcessFor($job->server(), $job->server() . "_out.txt", $job->server() . "_error.txt");
			if (!$proc->ping())
				continue;
			// check finished marker
			$finished = $job->checkProcessFinished();
			report("checked finished marker", 2);
			
			// check result image AGAIN?
			$result = False;
			if ($imageProcessingIsOnQueueManager || (!$imageProcessingIsOnQueueManager && $finished)) {
				$result = $job->checkResultImage();
				report("checked result image", 2);
			}
			
			// TODO check for redundant file retrieval
			/*if (!$imageProcessingIsOnQueueManager && $result) {
				$this->retrieveImagesFromServer($server_hostname, $username);
			}*/
			
			// notify user
			$startTime = $queue->startTime($job);
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
					$this->writeParameterFile($job, $startTime);
					if ($send_mail) $this->notifySuccess($job, $startTime);
					//if (file_exists($errorFile)) { 
					//    unlink($errorFile);
					//} 
					// make withdrawal of hours
					if ($use_accounting_system) {
						$this->makeWithdrawal($desc->owner(), $accounting_client_name, $startTime, $this->stopTime, $desc->credit(), $desc->group);
					}
				}
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

	function writeParameterFile($job, $startTime) {
		global $resultImagesOwnedByUser;
		$result = True;
		$desc = $job->description();
		$sourceFileName = $desc->sourceImageName();
		$destFileName = $desc->destinationImageName();
		$text = '';
		$text = $text . "Your job started at $startTime finished at " . date("l d of F Y h:i:s A") . ". ";
		$text = $text . "The image \n$sourceFileName\n has been successfully treated by Huygens.\n";
		$text = $text . "The resulting image is $destFileName.\n";
		$text = $text . $this->parameterText($job);
		$imageName = $desc->destinationImageName();
		$user = $desc->owner();
		$username = $user->name();
		$fileserver = new Fileserver($username);
		$path = $fileserver->destinationFolderFor($desc);
		$parameterFileName = $path . '/' . $imageName . '.txt';
		$file = fopen($parameterFileName, "w");
		$result = $result && (fwrite($file, $text) > 0);
		fclose($file);
		if ($resultImagesOwnedByUser) {
			chown($parameterFileName, $username);
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
		$errorFile = $job->server() . '_error.txt';
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
		$desc = $job->description();
		$user = $desc->owner();
		$emailAddress = $user->emailAddress();
		$sourceFileName = $desc->sourceImageName();
		//$destFileName = $desc->destinationImageName();
		$destFileName = $desc->destinationImageFullName();
		$text = "This is a mail generated automatically by huygens remote manager.\n";
		$text = $text . "Your job started at $startTime finished at " . date("l d of F Y h:i:s A") . ". ";
		$text = $text . "The image \n$sourceFileName\n has been successfully treated by Huygens.\n";
		$text = $text . "The resulting image is $destFileName.\n";
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
		$sourceFileName = $desc->sourceImageName();
		//$destFileName = $desc->destinationImageName();
		$text = "This is a mail generated automatically by huygens remote manager.\n";
		$text = $text . "Sorry, the processing of the image \n$sourceFileName\n has been terminated by an unknown error.\n";
		$text = $text . "Your job started at $startTime finished at " . date("l d of F Y h:i:s A") . ".";
		$text = $text . "\n";
		$text = $text . $message;
		$text = $text . "\n";
		$text = $text . "Best regards,\n";
		$text = $text . "Huygens Remote Manager\n";
		$text = $text . $this->parameterText($job);
		$text = $text . "\n";
		$text = $text . "The script that was executed while the error occured is:";
		$text = $text . "\n";
		$text = $text . $job->script();
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
		$sourceFileName = $desc->sourceImageName();
		//$destFileName = $desc->destinationImageName();
		$destFileName = $desc->sourceImageName();
		$text = "This is a mail generated automatically by huygens remote manager.\n";
		$text = $text . "The processing of the image \n$sourceFileName\n has been terminated with a warning: ";
		$text = $text . $message . "\n";
		$text = $text . "Huygens used a theoretical PSF instead of the measured PSF you provided.\n";
		$text = $text . "Your job started at $startTime finished at " . date("l d of F Y h:i:s A") . ".";
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

	function parameterText($job) {
		$desc = $job->description();
		$id = $desc->id();
		$pid = $job->pid();
		$server = $job->server();
		$result = '';
		$result = $result . "\n\nJob id: $id (pid $pid on $server)";
		$result = $result . "\nparameter used:\n";
		$result = $result . "\nparameter setting:\n";
		$parameterSetting = $desc->parameterSetting();
		$parameterSettingString = $parameterSetting->displayString();
		$result = $result . $parameterSettingString;
		$result = $result . "\ntask setting:\n";
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
				$proc = newExternalProcessFor($name, $name . "_out.txt", $name . "_error.txt");
				report("found free server", 2);
				if ($proc->ping()) {
					report("ping succeeded for $name", 2);
					$this->freeServer = $name;
					return True;
				}
			}
		}
		$this->freeServer = False;
		return $this->freeServer;
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

	function processCompoundJobs() {
		$queue = $this->queue;
		$compoundJobs = $queue->getCompoundJobs();
		foreach ($compoundJobs as $jobDescription) {
			$job = new Job($jobDescription);
			$job->createSubJobsOrScript();
		}
	}

	function run() {
		global $imageProcessingIsOnQueueManager;
		
		$queue = $this->queue;
		
		$this->waitForDatabaseConnection();
		$server = $queue->availableServer();
		// TODO refactor this in order to manage error logs per job
		foreach ($server as $name) {
			if (!$queue->isServerBusy($name)) {
				$errorFile = $name . "_error.txt";
				if (file_exists($errorFile)) {
					report("resetting error log for $name", 1);
					//unlink($errorFile);
				}
			}
		}
		
		report("Huygens Remote Manager started on " . date("l d of F Y h:i:s A") . "\n", 1);
		while (!$this->shallStop()) {
			set_time_limit(0);
			$this->queue = $queue;
			$result = True;
			
			// reduce the used cycles by going to sleep for one second
			if ($imageProcessingIsOnQueueManager) {
				sleep(1);
			}
			
			// Check if jobs finished and update the database. Inform the user via email.
			$this->updateJobAndServerStatus();
			report("Huygens server updated status", 2);
			
			// Process compound jobs
			$this->processCompoundJobs();
			
			// Read in a free huygens server
			while ($this->getFreeServer() && $this->nextJobFromQueue() != NULL) {
				report("using Huygens server: " . $this->freeServer, 2);
				
				// Read in a queued job 
				$job = $this->job;
				$desc = $job->description();
				$id = $desc->id();
				report("processing job " . $id . " on " . $job->server(), 1);
				
				// TODO check this <<
				// If the job is compound create sub jobs and remove job otherwise create script
				//$result = $result && $job->createSubJobsOrScript();
				$result = $job->createSubJobsOrScript();
				if (!$result || $desc->isCompound()) {
					error_log("error or compound job");
					continue;
				}
				// >>
				report("script has been created", 1);

				// Execute the script on the huygens server and update the database state
				$result = $result && $this->executeScript();
				if (!$result)
					continue;
				report("script has been executed", 1);
				$result = $result && $queue->startJob($this->job);
				report("job has been started (" . date("l d of F Y h:i:s A") . ")", 1);
			}
		}
		report("Huygens Remote Manager stopped via database switch on " . date("l d of F Y h:i:s A"), 1);
	}
}
?>
