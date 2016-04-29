<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;

require_once( "Shell.inc.php" );

/*!
   \class	JobQueue
   \brief	Manages the queue of deconvolution Jobs
 */
class JobQueue {

    /*!
       \brief	Constructor
     */
    public function __construct() {
    }

    /*!
       \brief	Return the timestamp of now()
       \return	Timestamp
     */
    public function timestampNowString() {
        $db = new DatabaseConnection();
        $date = $db->now();
        $ms = microtime();
        $ms = explode(" ", $ms);
        $ms = $ms[0];
        return $date . "." . substr($ms,2);
    }

    /*!
       \brief	Get names of all processing servers (independent of their status)
       \return	array of server names
     */
    function availableServer() {
        $db = new DatabaseConnection();
        $result = $db->availableServer();
        return $result;
    }

    /*!
       \brief	Returns all jobs from the queue, both compund and simple,
      			and the associated file names, ordered by priority
       \return	all jobs
     */
    function getContents() {
        $db = new DatabaseConnection();
        $rows = $db->getQueueContents();
        return $rows;
    }

    /*!
      \brief  Returns all file names associated to a job with given id
      \param	$id	Job id
      \return array of file names
    */
    public function getJobFilesFor($id) {
        $db = new DatabaseConnection();
        $files = $db->getJobFilesFor($id);
        return $files;
    }

    /*!
       \brief	Adds a Job for a JobDescription to the queue
       \param	$jobDescription	A JobDescription object
       \return	true if queuing the Job succeeded, false otherwise
     */
    public function queueJob( JobDescription $jobDescription) {
        $owner = $jobDescription->owner();
        $ownerName = $owner->name();
        $db = new DatabaseConnection();
        $result = $db->queueJob($jobDescription->id(), $ownerName);
        return $result;
    }

    /*!
       \brief	Starts a Job
       \param	$job	A Job object
       \return	true if starting the Job succeeded, false otherwise
     */
    public function startJob( Job $job) {
        $db = new DatabaseConnection();
        $pid = $job->pid();
        $result = $db->reserveServer($job->server(), $pid);
        $result = $result && $db->startJob($job);
        return $result;
    }

    /*!
       \brief	Gets the JobDescription for the next Job id from the database
       \return	a loaded JobDescription or null if no more jobs are in the queue
     */
    public function getNextJobDescription() {
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

    /*!
       \brief	Gets the compound jobs from the queue
       \return	array of JobDescriptions for compound Jobs
     */
    public function getCompoundJobs() {
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

    /*!
       \brief	Removes the Job that correspond o a given JobDescription
       \param	$jobDescription	A JobDescription object
       \return	true if Job removal was successful, false otherwise
     */
    public function removeJob( JobDescription $jobDescription) {
        $id = $jobDescription->id();
        $result = $this->removeJobWithId($id);
        return $result;
    }

    /*!
       \brief	Marks Jobs with given ids as 'broken' (i.e. to be removed)
       \param	$ids    Job ids
       \param   $owner  Name of the user who owns the Job
       \return	true if Job the job could be marked, false otherwise
     */
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

            // The front end should NOT try to kill the job, it may not work.
            // The Queue Manager will take care of it.
        }
        return $result;
    }

    /*!
       \brief	Kills Jobs with given id
       \param	$ids     Job ids (string or array of strings)
       \return	true if all Jobs were killed, false otherwise
     */
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
            $proc = newExternalProcessFor($server,
                                          $server . "_". $id . "_out.txt",
                                          $server . "_". $id . "_error.txt");
            $killed = $proc->killHucoreProcess($pid);
            $result = $result && $killed;
            
            // Clean the database and the error file
            $result = $result && $this->removeJobWithId($id);
            $result = $result && $db->markServerAsFree($server);
            $errorFile = $logdir . "/" . $server .  "_" .$id. "_error.txt";
            if (file_exists($errorFile)) {
                unlink($errorFile);
            }

            $proc->release();
        }
        return $result;
    }

    /*!
       \brief	Kills marked Jobs (i.e. those with status 'kill')
       \return	true if all marked Jobs were killed, false otherwise
     */
    function killMarkedJobs() {
        $db = new DatabaseConnection();
        $ids = $db->getJobIdsToKill();
        if ($ids != null && count($ids) > 0) {
            if ( $this->killJobs($ids) ) {
                report("running broken jobs killed and removed", 2);
                return True;
            } else {
                report("killing running broken jobs failed", 2);
                return False;
            }
        } else {
            return False;
        }
    }

    /*!
       \brief	Remove marked Jobs from the Queue (i.e. those 'kill'ed)
       \return	true if all marked Jobs were removed, false otherwise
     */
    function removeMarkedJobs() {
        $db = new DatabaseConnection();
        $ids = $db->getMarkedJobIds();
        foreach ($ids as $id) {
            $this->removeJobWithId($id);
        }
        if ($ids != null && count($ids) > 0) {
          return True;
        }
        return False;
    }

    /*!
       \brief	Remove Job with given id from the database
       \param   $id Job id
       \return	true if the Job were removed, false otherwise
     */
    function removeJobWithId($id) {
        $db = new DatabaseConnection();
        return $db->deleteJobFromTables($id);
    }

    /*!
       \brief	Stops and removes a Job from the Queue and the database
       \param   $job Job object
       \return	true if the Job were removed, false otherwise
     */
    function stopJob( Job $job ) {
        $db = new DatabaseConnection();
        $db->resetServer($job->server(), $job->pid());
        $this->removeJob($job->description());
        return $this->timestampNowString();
    }

    /*!
       \brief	Returns all running jobs from the database
       \return	array of Job objects
     */
    function runningJobs() {
        $db = new DatabaseConnection();
        $jobs = $db->getRunningJobs();
        return $jobs;
    }

    /*!
       \brief	Returns the start time of a given Job object
       \param   $job  Job object
       \return	start time (string)
     */
    function startTime( Job $job ) {
        $db = new DatabaseConnection();
        $date = $db->startTimeOf($job);
        return $date;
    }

    /*!
       \brief	Updates the estimated end time in the database
       \param   $id   Job id
       \param   $date Estimated end time (string)
       \return	query result
     */
    function updateEstimatedEndTime($id, $date) {
        $db = new DatabaseConnection();
        return $db->setJobEndTime($id, $date);
    }


    /*!
       \brief	Pauses the Job described by the given JobDescription
       \param   $jobDescription JobDescription object
       \return	query result
     */
    function pauseJob( JobDescription $jobDescription ) {
        $db = new DatabaseConnection();
        $result = $db->pauseJob($jobDescription->id());
        return $result;
    }

    /*!
       \brief	Restarts all paused Jobs
       \return	query result
     */
    function restartPausedJobs() {
        $db = new DatabaseConnection();
        $result = $db->restartPausedJobs();
        return $result;
    }

    /*!
       \brief	Checks whether server is busy
       \param   $name name of the server
       \return	true if the server is busy, false otherwise
     */
    function isServerBusy($name) {
        $db = new DatabaseConnection();
        $result = $db->isServerBusy($name);
        return $result;
    }

    /*!
       \brief	Checks whether the QueueManager is locked
       \return	true if the QueueManager is locked, false otherwise
     */
    function isLocked() {
        $db = new DatabaseConnection();
        $ans = $db->getSwitchStatus();
        $result = false;
        if ( $ans == "lck" ) {
            $result = true;
        }
        return $result;
    }

    /*!
       \brief	Locks the QueueManager
       \return	query result
     */
    function lock() {
        $db = new DatabaseConnection();
        $result = $db->setSwitchStatus("lck");
        return $result;
    }

    /*!
       \brief	Unlocks the QueueManager
       \return	query result
     */
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
