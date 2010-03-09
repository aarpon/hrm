<?php

// Module JobQueue.inc

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

Class JobQueue {
  function JobQueue() {
    
  } 
  
  function timestampNowString() {
    $db = new DatabaseConnection();
    $date = $db->now();
    $ms = microtime();
    $ms = explode(" ", $ms);
    $ms = $ms[0];
    return $date . "." . substr($ms,2); 
  } 
  
  function availableServer() {
    $db = new DatabaseConnection();
    $result = $db->availableServer();
    return $result;
  } 

  function getContents() {
    $db = new DatabaseConnection();
    $rows = $db->getQueueContents();    
    return $rows;
  } 

  function getJobFilesFor($id) {
    $db = new DatabaseConnection();
    $files = $db->getJobFilesFor($id);    
    return $files;    
  } 

  function queueJob($jobDescription) {
    $owner = $jobDescription->owner;
    $ownerName = $owner->name();
    $db = new DatabaseConnection();
    $result = $db->queueJob($jobDescription->id(), $ownerName);
    return $result;
  } 

  function startJob($job) {
    $db = new DatabaseConnection();
    $pid = $job->pid();
    $result = $db->reserveServer($job->server(), $pid); 
    $result = $result && $db->startJob($job);
    return $result;
   }

  function getNextJobDescription() {
    $db = new DatabaseConnection();
    $id = $db->getNextIdFromQueue();
    if ($id == NULL) {
      return NULL;
    } 
    $jobDescription = new JobDescription;
    $jobDescription->setId($id);
    $jobDescription->load();
    return $jobDescription;
  }

  function getCompoundJobs() {
    $db = new DatabaseConnection();
    $jobDescriptions = array();
    $rows = $db->getQueueJobs();
    foreach ($rows as $row) {
      $jobDescription = new JobDescription;
      $jobDescription->setId($row['id']);
      $jobDescription->load();
      if ($jobDescription->isCompound()) {
        $jobDescriptions[] = $jobDescription; 
      }  
    } 
    return $jobDescriptions;
  } 

  function removeJob($jobDescription) {
    $id = $jobDescription->id();
    $result = $this->removeJobWithId($id);
    return $result;
  } 

  function markJobsAsRemoved($ids) {  
    $result = True;
    if (count($ids)==0) return $result;
    $db = new DatabaseConnection();
    foreach($ids as $id) {  
        // loop through all the jobs selected, which have to be deleted
        $result = $result && $db->markJobAsRemoved($id);   

        // The front end should NOT try to kill the job, it may not work. The
        // Queue Manager will take care of it.
    } 
    return $result;
  }

  function killJobs($ids) {
      global $logdir;

      $result = True;
      if (count($ids)==0) return $result;
      $db = new DatabaseConnection();
      foreach($ids as $id) {  
          // Loop through all the jobs selected, which have to be killed and
          // deleted.

          $row = $db->getQueueContentsForId($id);
          $pid = $row['process_info'];
          $server = $row['server'];
          $proc = $this->newExternalProcess($server, 
                  $server . "_". $id . "_out.txt", 
                  $server . "_". $id . "_error.txt");
          $killed = $proc->killHucoreProcess($pid);
          report("Trying to kill running job with pid $pid: $killed",1);
          $result = $result && $killed;
          if ($killed) {
              $result = $result && $this->removeJobWithId($id);
              $result = $result && $db->markServerAsFree($server);
              $errorFile = $logdir . "/" . $server .  "_" .$id. "_error.txt";
              if (file_exists($errorFile)) unlink($errorFile);
          }
    } 
    return $result;
  }
  
  function newExternalProcess($host, $logfilename, $errfilename) {
	global $imageProcessingIsOnQueueManager;
	$db = new DatabaseConnection();
	$huscript_path = $db->huscriptPathOn($host);
	if ($imageProcessingIsOnQueueManager)
		$shell = new LocalExternalProcess($host, $huscript_path, $logfilename, $errfilename);
	else
		$shell = new ExternalProcess($host, $huscript_path, $logfilename, $errfilename);
	return $shell;
  } 

  function killMarkedJobs() {
    $db = new DatabaseConnection();
    $ids = $db->getJobIdsToKill();
    if ($ids != null && count($ids) > 0) {
        if ( $this->killJobs($ids) ) {
            report("running broken jobs killed and removed", 2);
        } else {
            report("killing running broken jobs failed", 2);
        }
    } else {
        return False;
    }
  }

  function removeMarkedJobs() {
    $db = new DatabaseConnection();
    $ids = $db->getMarkedJobIds();
    foreach ($ids as $id) {
      $this->removeJobWithId($id);
    }
    if ($ids != null && count($ids) > 0)
      return True;
    return False;
  } 

  function removeJobWithId($id) {
  	global $use_accounting_system;
    $result = True;
    $db = new DatabaseConnection();
    $tables = array('job_queue', 'job_files', 'job_parameter', 'job_parameter_setting', 'job_task_parameter', 'job_task_setting');
    $columns = array('id', 'job', 'setting', 'name', 'setting', 'name');
     if ($use_accounting_system) {
      $tables[] = 'job_accounting_data';
      $columns[] = 'id'; 
    } 
    $result = $result && $db->deleteFromTablesWhereColumnEquals($tables, $columns, $id);
    return $result; 
  } 
  
  function stopJob($job) {
    $db = new DatabaseConnection();
    $db->resetServer($job->server(), $job->pid());
    $this->removeJob($job->description());
    return $this->timestampNowString();
  } 

  function runningJobs() {
    $db = new DatabaseConnection();
    $jobs = $db->getRunningJobs();
    return $jobs;
  }

  function startTime($job) {
    $db = new DatabaseConnection();
    $date = $db->startTimeOf($job);    
    return $date;
  }
  
  function pauseJob($jobDescription) {
    $db = new DatabaseConnection();
    $result = $db->pauseJob($jobDescription->id());
    return $result;
  } 

  function restartPausedJobs() {
    $db = new DatabaseConnection();
    $result = $db->restartPausedJobs();
    return $result;    
  }
  
  function isServerBusy($name) {
    $db = new DatabaseConnection();
    $result = $db->isServerBusy($name);
    return $result;
  }  

  function isLocked() {
    $db = new DatabaseConnection();
    $ans = $db->getSwitchStatus();
    $result = false;
    if ( $ans == "lck" ) {
        $result = true;
    }
    return $result;
  }  

  function lock() {
    $db = new DatabaseConnection();
    $result = $db->setSwitchStatus("lck");
    return $result;
  }  

  function unlock() {
    $result = false;  
    if ($this->isLocked()) {
        $db = new DatabaseConnection();
        $result = $db->setSwitchStatus("on");
    }
    return $result;
  }



}

?>
