<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

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

  function markJobsAsRemoved($ids, $owner) {  
    $result = True;
    if (count($ids)==0) return $result;
    $db = new DatabaseConnection();
    foreach($ids as $id) {  
        // loop through all the jobs selected, which have to be deleted
        if ( $owner != "admin" && $db->getJobOwner($id) != $owner ) {
            continue;
        }
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
    $result = True;
    $db = new DatabaseConnection();
    $tables = array('job_queue', 'job_files', 'job_parameter', 'job_parameter_setting', 'job_task_parameter', 'job_task_setting');
    $columns = array('id', 'job', 'setting', 'name', 'setting', 'name');
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

  function updateEstimatedEndTime($id, $date) {
    $db = new DatabaseConnection();
    return $db->setJobEndTime($id, $date);
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
