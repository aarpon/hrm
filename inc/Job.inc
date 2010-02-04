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
		$this->script = "set id [pid]" . "\n" . 'puts "\npid=$id"' . "\n";
		$this->script = $this->script . "huOpt verb -mode noQs\n";
		$this->script = $this->script . "catch {\n";
		$this->script = $this->script . "huOpt gundo off\n";
		if ($setting->isMultiChannel() && ($setting->numberOfChannels() > 1)) {
			$this->script = $this->script . $desc->putMultiChannelScriptOn('', true);
		} else {
			$this->script = $this->script . $desc->putScriptOn('', true);
		}
		$this->script = $this->script . "}\n";
        $id = $desc->id();
        $finishedMarker = $desc->sourceFolder() . '.finished_' . "$id";
        $this->script = $this->script . "exec touch " . '"' . 
            $finishedMarker . '"' . "\n";
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
	
	// TODO refactor
	function checkResultImage() {
		global $imageProcessingIsOnQueueManager;
		global $copy_images_to_huygens_server;
		global $huygens_user;
		global $huygens_group;
		global $huygens_server_image_folder;
		global $image_destination;
		
		clearstatcache();
		
		$queue = new JobQueue();
		
		// Server name without proc number
		$server = $this->server;
		$s = split(" ", $server);
		$server_hostname = $s[0];
		
		$desc = $this->description();
		$user = $desc->owner();
		
		$fileserver = new Fileserver($user->name());
		$path = $fileserver->destinationFolderFor($desc);
		
		// TODO refactor JobDescription
		$resultImage = $desc->sourceImageShortName() . "*" . "_" . $desc->id() . "*";
		
		// If fileshare is not on the same host as Huygens
		if (!$imageProcessingIsOnQueueManager && $copy_images_to_huygens_server) {
			$image = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $desc->relativeSourcePath() . $resultImage;
			// escape special characters in image path
			$image = eregi_replace(" ", "\\ ", $image);
                        $image = str_replace(".ics",".i*s", $image);
			//error_log("Retrieving result image...");
			//error_log("sudo mkdir -p " . escapeshellarg($path));
			$result = exec("sudo mkdir -p " . escapeshellarg($path));
			//error_log($result);
			//error_log("(cd " . escapeshellarg($path) . " && scp " . $huygens_user . "@" . $server_hostname . ":" . escapeshellarg($image) . " .)");
			$result = exec("(cd " . escapeshellarg($path) . " && sudo scp " . $huygens_user . "@" . $server_hostname . ":" . escapeshellarg($image) . " .)");
			//error_log($result);
		}
		
		// TODO is checking for job id only a good idea?
		$fileNameExists = $fileserver->folderContains($path, $desc->id());
		// TODO is checking for new files a relevant criterion?
		//$newFileWritten = $fileserver->folderContainsNewerFile($path, $queue->startTime($this));
		$result = $fileNameExists/* || $newFileWritten*/;
		return $result;
	}

	function checkProcessFinished() {
		global $imageProcessingIsOnQueueManager;
		global $huygens_user;
		global $huygens_server_image_folder;
		global $image_source;
		
		clearstatcache();
		
		// Server name without proc number
		$server = $this->server;
		$s = split(" ", $server);
		$server_hostname = $s[0];
		
		$desc = $this->description();
		$user = $desc->owner();
		
		$fileserver = new Fileserver($user->name());
		$path = $fileserver->sourceFolder();
		
		$finishedMarker = ".finished_" . $desc->id();
		
		// If fileshare is not on the same host as Huygens
		if (!$imageProcessingIsOnQueueManager) {
			$marker = $huygens_server_image_folder . $user->name() . "/" . $image_source . "/" . $finishedMarker;
			$result = exec("ssh " . $huygens_user . "@" . $server_hostname . " ls " . $marker);
			//error_log("ssh " . $huygens_user . "@" . $server_hostname . " ls " . $marker);
			//error_log($result);
			if ($result == $marker) {
				exec("(cd " . $path . " && sudo scp " . $huygens_user . "@" . $server_hostname . ":" . $marker . " .)");
			}
		}
		
		// TODO check why $result is overwritten
		$result = file_exists($path . '/' . $finishedMarker);
		if ($imageProcessingIsOnQueueManager) {
			$proc = newExternalProcessFor($this->server(), $this->server() . "_out.txt", $this->server() . "_error.txt");
			$result = !$proc->existsHuygensProcess($this->pid());
			if (!$result && $proc->isHuygensProcessSleeping($this->pid())) {
				$proc->rewakeHuygensProcess($this->pid());
			}
		}
		return $result;
	}
}

?>
